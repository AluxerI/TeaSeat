<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DeliveryMethod;
use App\Models\AddressClient;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory;
use Illuminate\Support\Facades\Log;


class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected WarehouseService $warehouseService
    ) {}

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

        // 🎯 ОСНОВНАЯ ЛОГИКА: ЕСЛИ ЭТО ЗАКАЗ У ПОСТАВЩИКА
        if ($isSupplierOrder) {
            return $this->processSupplierOrder($cart, $shippingAddress, $deliveryMethod, $paymentMethod, $customerNotes);
        }

        // Старая логика для обычных заказов
        $warehouseAllocation = $this->warehouseService->determineWarehousesForOrder($cart, $shippingAddress);
        $this->warehouseService->reserveStockByAllocation($warehouseAllocation);

        $cart->update([
            'status' => Order::STATUS_PENDING,
            'shipping_address_id' => $shippingAddressId,
            'delivery_method_id' => $deliveryMethodId,
            'payment_method' => $paymentMethod,
            'shipping_cost' => $deliveryMethod->cost,
            'customer_notes' => $customerNotes,
            'confirmed_at' => now(),
        ]);

        $this->warehouseService->createPartialOrders($cart, $warehouseAllocation, $shippingAddress);

        return $cart->fresh(['items.product', 'deliveryMethod', 'shippingAddress']);
        });
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
        // Для заказа у поставщика НЕ резервируем товары на складе
        // и НЕ распределяем по складам

        $cart->update([
            'status' => Order::STATUS_PENDING,
            'shipping_address_id' => $shippingAddress->id,
            'delivery_method_id' => $deliveryMethod->id,
            'payment_method' => $paymentMethod,
            'shipping_cost' => $deliveryMethod->cost,
            'customer_notes' => $customerNotes,
            'is_supplier_order' => true, // ← ОТМЕТКА ЧТО ЭТО ЗАКАЗ У ПОСТАВЩИКА
            'internal_notes' => 'ЗАКАЗ У ПОСТАВЩИКА - требуется ручная обработка',
            'confirmed_at' => now(),
        ]);

        Log::info('Supplier order created', [
            'order_id' => $cart->id,
            'user_id' => $cart->user_id,
            'items_count' => $cart->items->count()
        ]);

        return $cart->fresh(['items.product', 'deliveryMethod', 'shippingAddress']);
    }

    public function getAvailableDeliveryMethods(int $userId, int $shippingAddressId): array
    {
        $shippingAddress = AddressClient::where('user_id', $userId)->findOrFail($shippingAddressId);

        return DeliveryMethod::active()
            ->get()
            ->filter(fn($method) => $method->isAvailableInCity($shippingAddress->city))
            ->values()
            ->toArray();
    }

    public function cancelOrder(int $userId, int $orderId): Order
    {
        return DB::transaction(function () use ($userId, $orderId) {
            $order = Order::where('user_id', $userId)
                ->where('status', '!=', Order::STATUS_CART)
                ->findOrFail($orderId);
        
            // Возвращаем товары на склад (если заказ не корзина)
            if ($order->warehouse_id && $order->status !== Order::STATUS_CART) {
                foreach ($order->items as $item) {
                    Inventory::where('product_id', $item->product_id)
                        ->where('warehouse_id', $order->warehouse_id)
                        ->increment('quantity', $item->quantity);
                }
            }
        
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now()
            ]);
        
            return $order;
        });
    }
}