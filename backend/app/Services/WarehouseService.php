<?php

namespace App\Services;

use App\Models\Order;
use App\Models\AddressClient;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\OrderProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WarehouseService
{
     public function __construct(
        protected SupplierOrderService $supplierOrderService
    ) {}

    public function determineWarehousesForOrder(Order $order, AddressClient $shippingAddress): array
    {
        $orderItems = $order->items()->with('product')->get();

        
        $cityWarehouses = Warehouse::where('city', $shippingAddress->city)
            ->active()
            ->get();
    
        if ($cityWarehouses->isEmpty()) {
            throw new \Exception("В городе {$shippingAddress->city} нет активных складов");
        }
    
        $warehouseAllocation = $this->allocateItemsToWarehouses($orderItems, $cityWarehouses);
        $this->validateAllocationCompleteness($warehouseAllocation, $orderItems, $shippingAddress->city);
    
        return $warehouseAllocation;
    }

    private function allocateItemsToWarehouses(Collection $orderItems, Collection $warehouses): array
    {
        $allocation = [];
        $remainingItems = $orderItems->pluck('quantity', 'product_id')->toArray();

        foreach ($warehouses as $warehouse) {
            $warehouseAllocation = ['warehouse_id' => $warehouse->id, 'items' => []];

            foreach ($orderItems as $item) {
                if (($remainingItems[$item->product_id] ?? 0) <= 0) continue;

                $availableQuantity = $warehouse->inventories()
                    ->where('product_id', $item->product_id)
                    ->value('quantity') ?? 0;

                if ($availableQuantity > 0) {
                    $quantityToAllocate = min($availableQuantity, $remainingItems[$item->product_id]);
                    
                    $warehouseAllocation['items'][] = [
                        'product_id' => $item->product_id,
                        'quantity' => $quantityToAllocate,
                        'order_product_id' => $item->id,
                    ];

                    $remainingItems[$item->product_id] -= $quantityToAllocate;
                }
            }

            if (!empty($warehouseAllocation['items'])) {
                $allocation[] = $warehouseAllocation;
            }

            if (array_sum($remainingItems) === 0) break;
        }

        return $allocation;
    }

    private function validateAllocationCompleteness(array $warehouseAllocation, Collection $orderItems, string $city): void
    {
        $allocatedQuantities = [];
        
        foreach ($warehouseAllocation as $allocation) {
            foreach ($allocation['items'] as $item) {
                $allocatedQuantities[$item['product_id']] = 
                    ($allocatedQuantities[$item['product_id']] ?? 0) + $item['quantity'];
            }
        }

        $unfulfilled = [];
        foreach ($orderItems as $item) {
            $allocated = $allocatedQuantities[$item->product_id] ?? 0;
            if ($allocated < $item->quantity) {
                $productName = $item->product->name ?? "Товар {$item->product_id}";
                $unfulfilled[] = "{$productName} (недостает: " . ($item->quantity - $allocated) . " шт.)";
            }
        }

        if (!empty($unfulfilled)) {
            throw new \Exception("Недостаточно товаров на складах в городе {$city}. Недоступны: " . implode(', ', $unfulfilled));
        }
    }

    public function reserveStockByAllocation(array $warehouseAllocation): void
    {
        foreach ($warehouseAllocation as $allocation) {
            foreach ($allocation['items'] as $item) {
                $inventory = Inventory::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $allocation['warehouse_id'])
                    ->firstOrFail();

                if ($inventory->quantity < $item['quantity']) {
                    throw new \Exception("Недостаточно товара на складе. Требуется: {$item['quantity']}, Доступно: {$inventory->quantity}");
                }

                $inventory->decrement('quantity', $item['quantity']);
            }
        }
    }

    public function createPartialOrders(Order $mainOrder, array $warehouseAllocation, AddressClient $shippingAddress): array
    {
        $orders = [];

        foreach ($warehouseAllocation as $index => $allocation) {
            if ($index === 0) {
                $this->updateMainOrderForWarehouse($mainOrder, $allocation);
                $orders[] = $mainOrder;
            } else {
                $orders[] = $this->createPartialOrder($mainOrder, $allocation, $index);
            }
        }

        return $orders;
    }

    private function updateMainOrderForWarehouse(Order $order, array $allocation): void
    {
        $order->update([
            'warehouse_id' => $allocation['warehouse_id'],
            'internal_notes' => ($order->internal_notes ?? '') . 
                "\nОсновной заказ выполняется со склада ID: {$allocation['warehouse_id']}"
        ]);
    }

    private function createPartialOrder(Order $mainOrder, array $allocation, int $index): Order
    {

        $partialOrder = Order::create([
            'user_id' => $mainOrder->user_id,
            'status' => Order::STATUS_PENDING,
            'shipping_address_id' => $mainOrder->shipping_address_id,
            'delivery_method_id' => $mainOrder->delivery_method_id,
            'warehouse_id' => $allocation['warehouse_id'],
            'payment_method' => $mainOrder->payment_method,
            'customer_notes' => $mainOrder->customer_notes,
            'internal_notes' => "Частичный заказ #{$index} от основного заказа #{$mainOrder->id}",
            'parent_order_id' => $mainOrder->id,
            'confirmed_at' => now(),
        ]);

        foreach ($allocation['items'] as $item) {
            $mainOrderItem = OrderProduct::find($item['order_product_id']);
            
            if ($mainOrderItem) {
                $partialOrder->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $mainOrderItem->unit_price,
                    'promotion_discount_percent' => $mainOrderItem->promotion_discount_percent ?? 0,
                    'personal_discount_percent' => $mainOrderItem->personal_discount_percent ?? 0,
                    'final_unit_price' => $mainOrderItem->final_unit_price ?? $mainOrderItem->unit_price,
                    'total_price' => $mainOrderItem->unit_price * $item['quantity'],
                ]);
            }
        }

        $this->recalculateOrderTotals($partialOrder);
        return $partialOrder;
    }

    private function recalculateOrderTotals(Order $order): void
    {
        $order->load('items');
        
        $productsTotal = $order->items->sum('total_price');
        $shippingCost = $order->deliveryMethod->cost ?? 0;

        $order->update([
            'products_total' => $productsTotal,
            'shipping_cost' => $shippingCost,
            'final_total' => $productsTotal + $shippingCost,
        ]);
    }

    /**
     * Распределить товар к поставщику
     */
    public function allocateToSupplier(Order $order, int $productId, int $quantity): array
    {
        $supplier = $this->findAvailableSupplier($productId);
        
        if (!$supplier) {
            throw new \Exception("Нет доступных поставщиков для товара {$productId}");
        }

        // Находим или создаем активный заказ поставщика
        $supplierOrder = $this->supplierOrderService->getOrCreateActiveOrder($supplier);
        
        // Добавляем товар в заказ поставщика
        $this->supplierOrderService->addItemToSupplierOrder($supplierOrder, [
            'product_id' => $productId,
            'customer_order_id' => $order->id,
            'quantity' => $quantity
        ]);

        // Обновляем заказ клиента
        $order->update([
            'supplier_order_id' => $supplierOrder->id,
            'is_supplier_order' => true
        ]);

        return [
            'warehouse_id' => $supplier->id,
            'is_supplier' => true,
            'supplier_order_id' => $supplierOrder->id,
            'estimated_delivery' => $supplierOrder->delivery_date,
            'items' => [[
                'product_id' => $productId,
                'quantity' => $quantity
            ]]
        ];
    }

    private function findAvailableSupplier(int $productId): ?Warehouse
    {
        return Warehouse::suppliers()
            ->active()
            ->whereHas('inventories', function($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->orderBy('lead_time_days')
            ->first();
    }
}