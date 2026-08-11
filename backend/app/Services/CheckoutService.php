<?php

namespace App\Services;

use App\Models\Order;
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
        protected GiftAvailabilityService $giftAvailabilityService
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
        ?array $discountSelection = null
    ): Order {
        return DB::transaction(function () use (
            $userId,
            $shippingAddressId,
            $deliveryMethodId,
            $paymentMethod,
            $customerNotes,
            $isSupplierOrder,
            $idempotencyKey,
            $discountSelection
        ) {
            if ($existingOrder = $this->findExistingCheckout($userId, $idempotencyKey)) {
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
                if ($existingOrder = $this->findExistingCheckout($userId, $idempotencyKey)) {
                    return $existingOrder;
                }

                throw new DomainException('Активная корзина не найдена');
            }

            $cart->load([
                'items.product.inventories.warehouse',
                'items.product.suppliers',
                'gifts.items.product',
            ]);
            
            if ($cart->items->isEmpty()) {
                throw new DomainException('Корзина пуста');
            }

            $shippingAddress = AddressClient::where('user_id', $userId)->findOrFail($shippingAddressId);
            $deliveryMethod = DeliveryMethod::active()->findOrFail($deliveryMethodId);

            if (!$deliveryMethod->isAvailableInCity($shippingAddress->city)) {
                throw new DomainException("Способ доставки '{$deliveryMethod->name}' недоступен в городе {$shippingAddress->city}");
            }

            // Цена и лимиты скидок проверяются в той же транзакции, что и остатки.
            // Поэтому checkout никогда не доверяет цене, сохранённой в корзине ранее.
            $user = User::findOrFail($userId);
            $pricingQuote = $this->pricingService->quoteOrder(
                $cart,
                $user,
                (float) $deliveryMethod->cost,
                $discountSelection
            );
            $this->pricingService->applyQuoteToOrder($cart, $pricingQuote);
            $this->pricingService->consumeUsage($user, $pricingQuote);

            // Если это заказ у поставщика
            if ($isSupplierOrder) {
                return $this->processSupplierOrder(
                    $cart,
                    $shippingAddress,
                    $deliveryMethod,
                    $paymentMethod,
                    $customerNotes,
                    $idempotencyKey
                );
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
                'warehouse_id' => null,
                'confirmed_at' => now(),
            ]);

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

    private function findExistingCheckout(int $userId, string $idempotencyKey): ?Order
    {
        if ($idempotencyKey === '') {
            return null;
        }

        return Order::where('user_id', $userId)
            ->where('checkout_idempotency_key', $idempotencyKey)
            ->whereNull('parent_order_id')
            ->first()
            ?->load([
                'items.product',
                'gifts.items.product',
                'deliveryMethod',
                'shippingAddress',
                'partialOrders.items.product',
                'partialOrders.warehouse',
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
        string $idempotencyKey
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
            'internal_notes' => 'ЗАКАЗ У ПОСТАВЩИКА - требуется ручная обработка',
            'confirmed_at' => now(),
        ]);
        
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
                    ];
                })
                ->toArray();
        });
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
