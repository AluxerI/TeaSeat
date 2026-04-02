<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DeliveryMethod;
use App\Models\AddressClient;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected WarehouseService $warehouseService,
        protected LocationService $locationService,
        protected SupplierOrderService $supplierOrderService
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
        bool $isSupplierOrder = false
    ): Order {
        return DB::transaction(function () use (
            $userId, $shippingAddressId, $deliveryMethodId, $paymentMethod, $customerNotes, $isSupplierOrder
        ) {
            $cart = $this->cartService->getCart($userId);
            
            if ($cart->items->isEmpty()) {
                throw new \Exception('Корзина пуста');
            }

            $shippingAddress = AddressClient::where('user_id', $userId)->findOrFail($shippingAddressId);
            $deliveryMethod = DeliveryMethod::active()->findOrFail($deliveryMethodId);

            if (!$deliveryMethod->isAvailableInCity($shippingAddress->city)) {
                throw new \Exception("Способ доставки '{$deliveryMethod->name}' недоступен в городе {$shippingAddress->city}");
            }

            // Если это заказ у поставщика
            if ($isSupplierOrder) {
                return $this->processSupplierOrder($cart, $shippingAddress, $deliveryMethod, $paymentMethod, $customerNotes);
            }

            // Обычный заказ - проверяем доступность товаров в городе
            $this->validateOrderAvailability($cart, $shippingAddress->city);
            
            // Определяем склады для заказа
            $warehouseAllocation = $this->warehouseService->determineWarehousesForOrder($cart, $shippingAddress);
            
            // Резервируем товары
            $this->warehouseService->reserveStockByAllocation($warehouseAllocation);

            // Обновляем заказ
            $cart->update([
                'status' => Order::STATUS_PENDING,
                'shipping_address_id' => $shippingAddressId,
                'delivery_method_id' => $deliveryMethodId,
                'payment_method' => $paymentMethod,
                'shipping_cost' => $deliveryMethod->cost,
                'customer_notes' => $customerNotes,
                'confirmed_at' => now(),
            ]);

            // Создаем частичные заказы по складам
            $this->warehouseService->createPartialOrders($cart, $warehouseAllocation, $shippingAddress);
            
            // Очищаем кеш корзины
            $this->cartService->clearCartCache($userId);

            return $cart->fresh(['items.product', 'deliveryMethod', 'shippingAddress']);
        });
    }

    /**
     * Проверить доступность всех товаров в корзине в городе
     */
    private function validateOrderAvailability(Order $cart, string $city): void
    {
        foreach ($cart->items as $item) {
            $availableInCity = $this->locationService->getProductQuantityInCity($item->product, $city);
            
            if ($availableInCity < $item->quantity) {
                $productName = $item->product->name;
                throw new \Exception(
                    "Товар '{$productName}' недоступен в городе {$city} в нужном количестве. " .
                    "Доступно: {$availableInCity}, требуется: {$item->quantity}"
                );
            }
        }
    }

    /**
     * Обработать заказ у поставщика
     */
    private function processSupplierOrder(
        Order $cart, 
        AddressClient $shippingAddress, 
        DeliveryMethod $deliveryMethod,
        string $paymentMethod,
        ?string $customerNotes
    ): Order {
        // Проверяем доступность товаров у поставщиков
        $supplierAllocations = [];
        
        foreach ($cart->items as $item) {
            $isAvailableFromSupplier = $this->locationService->isProductAvailableFromSuppliers($item->product);
            
            if (!$isAvailableFromSupplier) {
                $productName = $item->product->name;
                throw new \Exception("Товар '{$productName}' недоступен для заказа у поставщиков");
            }
            
            // Находим лучшего поставщика для товара
            $bestSupplier = $this->locationService->getBestSupplierForProduct($item->product);
            
            if (!$bestSupplier) {
                $productName = $item->product->name;
                throw new \Exception("Не найден поставщик для товара '{$productName}'");
            }
            
            $supplierAllocations[$bestSupplier->id][] = [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'order_product_id' => $item->id
            ];
        }
        
        // Обновляем заказ
        $cart->update([
            'status' => Order::STATUS_PENDING,
            'shipping_address_id' => $shippingAddress->id,
            'delivery_method_id' => $deliveryMethod->id,
            'payment_method' => $paymentMethod,
            'shipping_cost' => $deliveryMethod->cost,
            'customer_notes' => $customerNotes,
            'is_supplier_order' => true,
            'internal_notes' => 'ЗАКАЗ У ПОСТАВЩИКА - требуется ручная обработка',
            'confirmed_at' => now(),
        ]);
        
        // Создаем заказы поставщикам
        foreach ($supplierAllocations as $supplierId => $items) {
            $supplier = \App\Models\Warehouse::find($supplierId);
            
            if ($supplier) {
                $supplierOrder = $this->supplierOrderService->getOrCreateActiveOrder($supplier);
                
                foreach ($items as $item) {
                    $this->supplierOrderService->addItemToSupplierOrder($supplierOrder, [
                        'product_id' => $item['product_id'],
                        'customer_order_id' => $cart->id,
                        'quantity' => $item['quantity']
                    ]);
                }
            }
        }
        
        // Очищаем кеш корзины
        $this->cartService->clearCartCache($cart->user_id);
        
        Log::info('Supplier order created', [
            'order_id' => $cart->id,
            'user_id' => $cart->user_id,
            'items_count' => $cart->items->count()
        ]);

        return $cart->fresh(['items.product', 'deliveryMethod', 'shippingAddress']);
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
                ->where('status', '!=', Order::STATUS_CART)
                ->findOrFail($orderId);
        
            // Возвращаем товары на склад (если заказ не у поставщика)
            if (!$order->is_supplier_order && $order->warehouse_id) {
                foreach ($order->items as $item) {
                    Inventory::where('product_id', $item->product_id)
                        ->where('warehouse_id', $order->warehouse_id)
                        ->increment('quantity', $item->quantity);
                    
                    // Очищаем кеш количества товара
                    Cache::forget("product_{$item->product_id}_total_quantity");
                }
            }
        
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now()
            ]);
            
            // Очищаем кеш
            Cache::forget("user_{$userId}_orders");
        
            return $order;
        });
    }
}