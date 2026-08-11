<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderGift;
use App\Models\OrderProduct;
use App\Models\DeliveryMethod;
use App\Models\AddressClient;
use App\Models\OrderStatusHistory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use DomainException;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected PricingService $pricingService,
        protected WarehouseService $warehouseService,
        protected LocationService $locationService,
        protected SupplierOrderService $supplierOrderService,
        protected GiftAvailabilityService $giftAvailabilityService,
        protected DeliveryScheduleService $deliveryScheduleService,
        protected CartSelectionService $cartSelectionService
    ) {}

    /**
     * Оформить заказ
     */
    public function checkout(
        int $userId,
        int $shippingAddressId,
        int $deliveryMethodId,
        string $paymentMethod,
        ?string $customerNotes = null,
        bool $isSupplierOrder = false,
        string $idempotencyKey = '',
        ?array $discountSelection = null,
        ?string $scheduledDeliveryDate = null,
        ?int $deliveryTimeSlotId = null,
        ?array $cartItemIds = null,
        ?array $cartGiftIds = null
    ): Order {
        $selectionHash = $this->cartSelectionService->hash(
            $cartItemIds,
            $cartGiftIds
        );
        return DB::transaction(function () use (
            $userId,
            $shippingAddressId,
            $deliveryMethodId,
            $paymentMethod,
            $customerNotes,
            $isSupplierOrder,
            $idempotencyKey,
            $discountSelection,
            $scheduledDeliveryDate,
            $deliveryTimeSlotId,
            $cartItemIds,
            $cartGiftIds,
            $selectionHash
        ) {
            if ($existingOrder = $this->findExistingCheckout(
                $userId,
                $idempotencyKey,
                $selectionHash,
                $cartItemIds !== null || $cartGiftIds !== null
            )) {
                return $existingOrder;
            }

            // Блокируем корзину: два параллельных checkout одного пользователя
            // не смогут одновременно превратить её в заказ.
            $cart = Order::where('user_id', $userId)
                ->where('status', Order::STATUS_CART)
                ->whereNull('parent_order_id')
                ->lockForUpdate()
                ->first();

            if (!$cart) {
                // Повторная проверка нужна для запроса, который ожидал блокировку:
                // первый запрос уже мог завершить checkout с тем же ключом.
                if ($existingOrder = $this->findExistingCheckout(
                    $userId,
                    $idempotencyKey,
                    $selectionHash,
                    $cartItemIds !== null || $cartGiftIds !== null
                )) {
                    return $existingOrder;
                }

                throw new DomainException('Активная корзина не найдена');
            }

            // При выборочном checkout исходная корзина остаётся активной.
            // Поэтому ожидавший её блокировку повторный запрос обязан ещё раз
            // проверить ключ, даже если корзина по-прежнему существует.
            if ($existingOrder = $this->findExistingCheckout(
                $userId,
                $idempotencyKey,
                $selectionHash,
                $cartItemIds !== null || $cartGiftIds !== null
            )) {
                return $existingOrder;
            }

            // Изменение количества или удаление выбранной строки не должно
            // вклиниться между проверкой состава и переносом в заказ.
            OrderProduct::query()
                ->where('order_id', $cart->id)
                ->lockForUpdate()
                ->get();
            OrderGift::query()
                ->where('order_id', $cart->id)
                ->lockForUpdate()
                ->get();

            $cart->load([
                'items.product.inventories.warehouse',
                'items.product.suppliers',
                'gifts.items.product',
            ]);
            
            if ($cart->items->isEmpty()) {
                throw new DomainException('Корзина пуста');
            }

            $sourceCart = $cart;
            $resolvedSelection = $this->cartSelectionService->resolve(
                $sourceCart,
                $cartItemIds,
                $cartGiftIds
            );
            if ($resolvedSelection['explicit']) {
                $cart = $this->createOrderFromSelection(
                    $sourceCart,
                    $resolvedSelection['order']
                );
            }

            $shippingAddress = AddressClient::where('user_id', $userId)->findOrFail($shippingAddressId);
            $deliveryMethod = DeliveryMethod::active()->findOrFail($deliveryMethodId);

            if (!$deliveryMethod->isAvailableInCity($shippingAddress->city)) {
                throw new DomainException("Способ доставки '{$deliveryMethod->name}' недоступен в городе {$shippingAddress->city}");
            }

            // Строка расписания блокируется до конца checkout. Поэтому два
            // параллельных заказа не смогут занять последнее место интервала.
            $deliverySelection = $this->deliveryScheduleService->reserveSelection(
                $deliveryMethod,
                $scheduledDeliveryDate,
                $deliveryTimeSlotId,
                $cart->id
            );

            // Цена и лимиты скидок проверяются в той же транзакции, что и остатки.
            // Поэтому checkout никогда не доверяет цене, сохранённой в корзине ранее.
            $user = User::findOrFail($userId);
            $pricingQuote = $this->pricingService->quoteOrder(
                $cart,
                $user,
                (float) $deliveryMethod->cost,
                $discountSelection
            );
            $pricingQuote['cart_selection'] = [
                'explicit' => $resolvedSelection['explicit'],
                'cart_item_ids' => $resolvedSelection['item_ids'],
                'cart_gift_ids' => $resolvedSelection['gift_ids'],
            ];
            $this->pricingService->applyQuoteToOrder($cart, $pricingQuote);
            $this->pricingService->consumeUsage($user, $pricingQuote);

            // Если это заказ у поставщика
            if ($isSupplierOrder) {
                $supplierOrder = $this->processSupplierOrder(
                    $cart,
                    $shippingAddress,
                    $deliveryMethod,
                    $paymentMethod,
                    $customerNotes,
                    $idempotencyKey,
                    $selectionHash,
                    $deliverySelection
                );
                if ($resolvedSelection['explicit']) {
                    $this->cartService->refreshAfterPartialCheckout($sourceCart);
                }
                return $supplierOrder;
            }

            // Обычный заказ - проверяем доступность товаров в городе
            $this->validateOrderAvailability($cart, $shippingAddress->city);
            
            // Определяем склады для заказа
            $warehouseAllocation = $this->warehouseService->determineWarehousesForOrder($cart, $shippingAddress);

            // Обновляем заказ
            $cart->update([
                'sales_channel' => Order::SALES_CHANNEL_ONLINE,
                'status' => Order::STATUS_PENDING,
                'shipping_address_id' => $shippingAddressId,
                'delivery_method_id' => $deliveryMethodId,
                'payment_method' => $paymentMethod,
                'shipping_cost' => $deliveryMethod->cost,
                'customer_notes' => $customerNotes,
                'checkout_idempotency_key' => $idempotencyKey,
                'checkout_selection_hash' => $selectionHash,
                'warehouse_id' => null,
                'confirmed_at' => now(),
            ] + $deliverySelection);

            // Создаем частичные заказы по складам
            $partialOrders = $this->warehouseService->createPartialOrders(
                $cart,
                $warehouseAllocation,
                $shippingAddress
            );

            // Физический остаток не меняется до завершения сборки:
            // checkout создаёт только складской резерв.
            $this->warehouseService->reserveOnlineStockForOrders(
                $partialOrders,
                $userId
            );
            $cart->update(['stock_reserved_at' => now()]);
            
            // Очищаем кеш корзины
            $this->cartService->clearCartCache($userId);
            if ($resolvedSelection['explicit']) {
                $this->cartService->refreshAfterPartialCheckout($sourceCart);
            }

            return $cart->fresh([
                'items.product',
                'gifts.items.product',
                'deliveryMethod',
                'shippingAddress',
                'partialOrders.items.product',
                'partialOrders.warehouse',
            ]);
        });
    }

    private function findExistingCheckout(
        int $userId,
        string $idempotencyKey,
        string $selectionHash,
        bool $explicitSelection
    ): ?Order
    {
        if ($idempotencyKey === '') {
            return null;
        }

        $order = Order::where('user_id', $userId)
            ->where('checkout_idempotency_key', $idempotencyKey)
            ->whereNull('parent_order_id')
            ->first();
        if (!$order) {
            return null;
        }
        if ($order->checkout_selection_hash === null && $explicitSelection) {
            throw new DomainException(
                'Idempotency-Key уже использован для другого состава заказа'
            );
        }
        if ($order->checkout_selection_hash !== null
            && !hash_equals($order->checkout_selection_hash, $selectionHash)) {
            throw new DomainException(
                'Idempotency-Key уже использован для другого состава заказа'
            );
        }

        return $order->load([
                'items.product',
                'gifts.items.product',
                'deliveryMethod',
                'shippingAddress',
                'partialOrders.items.product',
                'partialOrders.warehouse',
            ]);
    }

    private function createOrderFromSelection(Order $sourceCart, Order $selection): Order
    {
        $checkoutOrder = Order::create([
            'user_id' => $sourceCart->user_id,
            'sales_channel' => Order::SALES_CHANNEL_ONLINE,
            'status' => Order::STATUS_CART,
            'products_total' => 0,
            'promotion_discount' => 0,
            'personal_discount' => 0,
            'cart_discount' => 0,
            'shipping_cost' => 0,
            'shipping_discount' => 0,
            'final_total' => 0,
        ]);

        $giftIds = $selection->gifts->pluck('id')->all();
        $itemIds = $selection->items->pluck('id')->all();
        if ($giftIds !== []) {
            OrderGift::query()
                ->where('order_id', $sourceCart->id)
                ->whereIn('id', $giftIds)
                ->update(['order_id' => $checkoutOrder->id]);
        }
        OrderProduct::query()
            ->where('order_id', $sourceCart->id)
            ->whereIn('id', $itemIds)
            ->update(['order_id' => $checkoutOrder->id]);

        return $checkoutOrder->fresh([
            'items.product.inventories.warehouse',
            'items.product.suppliers',
            'gifts.items.product',
        ]);
    }

    /**
     * Проверить доступность всех товаров в корзине в городе
     */
    private function validateOrderAvailability(Order $cart, string $city): void
    {
        // Одинаковый SKU может находиться отдельно и в нескольких подарках.
        // Проверяем общую потребность, а не каждую строку изолированно.
        $this->giftAvailabilityService->assertCartAvailable($cart, $city);
    }

    /**
     * Обработать заказ у поставщика
     */
    private function processSupplierOrder(
        Order $cart, 
        AddressClient $shippingAddress, 
        DeliveryMethod $deliveryMethod,
        string $paymentMethod,
        ?string $customerNotes,
        string $idempotencyKey,
        string $selectionHash,
        array $deliverySelection
    ): Order {
        // Проверяем доступность товаров у поставщиков
        $supplierAllocations = [];
        
        foreach ($cart->items as $item) {
            $isAvailableFromSupplier = $this->locationService->isProductAvailableFromSuppliers($item->product);
            
            if (!$isAvailableFromSupplier) {
                $productName = $item->product->name;
                throw new DomainException("Товар '{$productName}' недоступен для заказа у поставщиков");
            }
            
            // Находим лучшего поставщика для товара
            $bestSupplier = $this->locationService->getBestSupplierForProduct($item->product);
            
            if (!$bestSupplier) {
                $productName = $item->product->name;
                throw new DomainException("Не найден поставщик для товара '{$productName}'");
            }
            
            $supplierAllocations[$bestSupplier->id][] = [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'order_product_id' => $item->id
            ];
        }
        
        // Обновляем заказ
        $cart->update([
            'sales_channel' => Order::SALES_CHANNEL_ONLINE,
            'status' => Order::STATUS_PENDING,
            'shipping_address_id' => $shippingAddress->id,
            'delivery_method_id' => $deliveryMethod->id,
            'payment_method' => $paymentMethod,
            'shipping_cost' => $deliveryMethod->cost,
            'customer_notes' => $customerNotes,
            'is_supplier_order' => true,
            'checkout_idempotency_key' => $idempotencyKey,
            'checkout_selection_hash' => $selectionHash,
            'internal_notes' => 'ЗАКАЗ У ПОСТАВЩИКА - требуется ручная обработка',
            'confirmed_at' => now(),
        ] + $deliverySelection);
        
        // Создаем заказы поставщикам
        foreach ($supplierAllocations as $supplierId => $items) {
            $supplier = Supplier::findOrFail($supplierId);
            $supplierOrder = $this->supplierOrderService->getOrCreateActiveOrder($supplier);
            
            foreach ($items as $item) {
                $this->supplierOrderService->addItemToSupplierOrder($supplierOrder, [
                    'product_id' => $item['product_id'],
                    'customer_order_id' => $cart->id,
                    'quantity' => $item['quantity']
                ]);
            }
        }
        
        // Очищаем кеш корзины
        $this->cartService->clearCartCache($cart->user_id);
        
        Log::info('Supplier order created', [
            'order_id' => $cart->id,
            'user_id' => $cart->user_id,
            'items_count' => $cart->items->count()
        ]);

        return $cart->fresh(['items.product', 'gifts.items.product', 'deliveryMethod', 'shippingAddress']);
    }

    /**
     * Получить доступные способы доставки
     */
    public function getAvailableDeliveryMethods(int $userId, int $shippingAddressId): array
    {
        $cacheKey = "delivery_methods_user_{$userId}_address_{$shippingAddressId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId, $shippingAddressId) {
            $shippingAddress = AddressClient::where('user_id', $userId)->findOrFail($shippingAddressId);

            return DeliveryMethod::active()
                ->get()
                ->filter(fn($method) => $method->isAvailableInCity($shippingAddress->city))
                ->values()
                ->map(function($method) {
                    return [
                        'id' => $method->id,
                        'name' => $method->name,
                        'description' => $method->description,
                        'cost' => (float) $method->cost,
                        'type' => $method->type,
                        'provider_code' => $method->provider_code,
                        'estimated_days' => $method->getEstimatedDaysFormatted(),
                        'requires_scheduling' => $method->requiresScheduling(),
                        'booking_horizon_days' => $method->requiresScheduling()
                            ? max(1, (int) config('delivery.booking_horizon_days', 30))
                            : null,
                    ];
                })
                ->toArray();
        });
    }

    public function getAvailableDeliverySlots(
        int $userId,
        int $shippingAddressId,
        int $deliveryMethodId
    ): array {
        $shippingAddress = AddressClient::where('user_id', $userId)
            ->findOrFail($shippingAddressId);
        $deliveryMethod = DeliveryMethod::active()->findOrFail($deliveryMethodId);

        if (!$deliveryMethod->isAvailableInCity($shippingAddress->city)) {
            throw new DomainException(
                "Способ доставки '{$deliveryMethod->name}' недоступен в городе {$shippingAddress->city}"
            );
        }
        if (!$deliveryMethod->requiresScheduling()) {
            throw new DomainException(
                'Для этого способа доставки календарь интервалов не используется'
            );
        }

        return [
            'delivery_method' => [
                'id' => (int) $deliveryMethod->id,
                'name' => $deliveryMethod->name,
                'type' => $deliveryMethod->type,
            ],
            'booking_horizon_days' => max(
                1,
                (int) config('delivery.booking_horizon_days', 30)
            ),
            'dates' => $this->deliveryScheduleService
                ->availableDates($deliveryMethod),
        ];
    }

    /**
     * Отменить заказ
     */
    public function cancelOrder(int $userId, int $orderId): Order
    {
        return DB::transaction(function () use ($userId, $orderId) {
            $order = Order::where('user_id', $userId)
                ->where('sales_channel', Order::SALES_CHANNEL_ONLINE)
                ->whereNull('parent_order_id')
                ->lockForUpdate()
                ->findOrFail($orderId);

            return $this->cancelLockedOrder(
                $order,
                $userId,
                'Заказ отменён покупателем'
            );
        });
    }

    public function cancelOrderByManager(
        int $orderId,
        int $managerId,
        ?string $reason = null
    ): Order {
        return DB::transaction(function () use ($orderId, $managerId, $reason) {
            $requestedOrder = Order::query()->findOrFail($orderId);
            if ($requestedOrder->sales_channel === Order::SALES_CHANNEL_SELLER) {
                throw new DomainException(
                    'Продажа продавца обрабатывается через workflow fulfillment issues'
                );
            }
            $mainOrderId = $requestedOrder->parent_order_id ?? $requestedOrder->id;

            $order = Order::whereNull('parent_order_id')
                ->lockForUpdate()
                ->findOrFail($mainOrderId);

            $notes = 'Отменён менеджером ID: ' . $managerId .
                '. Причина: ' . ($reason ?? 'не указана');

            return $this->cancelLockedOrder(
                $order,
                $managerId,
                $notes,
                true
            );
        });
    }

    private function cancelLockedOrder(
        Order $order,
        int $actorId,
        string $notes,
        bool $managerCancellation = false
    ): Order {
        if ($order->status === Order::STATUS_CANCELLED) {
            return $order->fresh([
                'items.product',
                'gifts.items.product',
                'deliveryMethod',
                'shippingAddress',
                'partialOrders.items.product',
                'partialOrders.warehouse',
            ]);
        }

        $canBeCancelled = $managerCancellation
            ? $order->canBeCancelledByManager()
            : $order->canBeCancelled();
        if (!$canBeCancelled) {
            throw new DomainException('Невозможно отменить заказ в текущем статусе');
        }

        $partialOrders = Order::where('parent_order_id', $order->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->with(['items', 'warehouse'])
            ->get();

        // Поддержка старых заказов, где основной order одновременно был
        // исполнением первого склада.
        if ($partialOrders->isEmpty() && !$order->is_supplier_order && $order->warehouse_id) {
            $order->loadMissing(['items', 'warehouse']);
            $this->warehouseService->releaseOnlineStockForOrder($order, $actorId);
        }

        foreach ($partialOrders as $partialOrder) {
            if ($partialOrder->status === Order::STATUS_CANCELLED) {
                continue;
            }

            if (!$partialOrder->is_supplier_order) {
                $this->warehouseService->releaseOnlineStockForOrder(
                    $partialOrder,
                    $actorId
                );
            }

            $oldStatus = $partialOrder->status;
            $partialOrder->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
            $this->recordCancellation($partialOrder, $oldStatus, $actorId, $notes);
        }

        $oldStatus = $order->status;
        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'stock_released_at' => $order->stock_reserved_at ? now() : null,
            'internal_notes' => trim(($order->internal_notes ?? '') . "\n" . $notes),
        ]);
        $this->pricingService->releaseUsage($order);
        $this->recordCancellation($order, $oldStatus, $actorId, $notes);

        Cache::forget("user_{$order->user_id}_orders");

        return $order->fresh([
            'items.product',
            'gifts.items.product',
            'deliveryMethod',
            'shippingAddress',
            'partialOrders.items.product',
            'partialOrders.warehouse',
        ]);
    }

    private function recordCancellation(
        Order $order,
        string $oldStatus,
        int $actorId,
        string $notes
    ): void {
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $oldStatus,
            'to_status' => Order::STATUS_CANCELLED,
            'changed_by' => $actorId,
            'notes' => $notes,
        ]);
    }
}
