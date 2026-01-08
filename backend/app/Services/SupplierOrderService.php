<?php

namespace App\Services;

use App\Models\SupplierOrder;
use App\Models\Supplier;
use App\Models\SupplierOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierOrderService
{
    /**
     * Найти или создать активный заказ поставщика
     */
    public function getOrCreateActiveOrder(Supplier $supplier): SupplierOrder
    {
        return DB::transaction(function () use ($supplier) {
            // Ищем активный заказ
            $activeOrder = $supplier->activeSupplierOrders()->first();
            
            if ($activeOrder) {
                return $activeOrder;
            }

            // Создаем новый заказ
            $nextOrderDate = $supplier->getNextOrderDate();
            $deliveryDate = $nextOrderDate->copy()->addDays($supplier->lead_time_days);

            return SupplierOrder::create([
                'supplier_id' => $supplier->id,
                'scheduled_date' => $nextOrderDate,
                'delivery_date' => $deliveryDate,
                'status' => SupplierOrder::STATUS_CONSOLIDATING,
                'total_quantity' => 0,
                'total_amount' => 0,
                'customer_orders_count' => 0
            ]);
        });
    }

    /**
     * Добавить товар в заказ поставщика
     */
    public function addItemToSupplierOrder(SupplierOrder $supplierOrder, array $itemData): SupplierOrderItem
    {
        return DB::transaction(function () use ($supplierOrder, $itemData) {
            // Создаем запись товара
            $orderItem = SupplierOrderItem::create([
                'supplier_order_id' => $supplierOrder->id,
                'product_id' => $itemData['product_id'],
                'customer_order_id' => $itemData['customer_order_id'],
                'quantity' => $itemData['quantity'],
                'unit_cost' => $itemData['unit_cost'] ?? null
            ]);

            // Обновляем статистику заказа
            $this->updateSupplierOrderStats($supplierOrder);

            return $orderItem;
        });
    }

    /**
     * Обновить статистику заказа поставщика
     */
    public function updateSupplierOrderStats(SupplierOrder $supplierOrder): void
    {
        $totalQuantity = $supplierOrder->items()->sum('quantity');
        $customerOrdersCount = $supplierOrder->customerOrders()->distinct()->count('customer_order_id');

        $supplierOrder->update([
            'total_quantity' => $totalQuantity,
            'customer_orders_count' => $customerOrdersCount
        ]);
    }

    /**
     * Обработать запланированные заказы поставщиков
     */
    public function processScheduledOrders(): void
    {
        $today = now()->format('Y-m-d');
        
        $ordersToProcess = SupplierOrder::where('scheduled_date', $today)
            ->where('status', SupplierOrder::STATUS_CONSOLIDATING)
            ->with(['supplier', 'items.product'])
            ->get();

        foreach ($ordersToProcess as $supplierOrder) {
            $this->processSupplierOrder($supplierOrder);
        }
    }

    /**
     * Обработать конкретный заказ поставщика
     */
    private function processSupplierOrder(SupplierOrder $supplierOrder): void
    {
        DB::transaction(function () use ($supplierOrder) {
            // Проверяем минимальный заказ
            if ($supplierOrder->total_quantity < $supplierOrder->supplier->min_order_quantity) {
                $this->cancelSupplierOrder($supplierOrder);
                return;
            }

            // Меняем статус на "заказан"
            $supplierOrder->update(['status' => SupplierOrder::STATUS_ORDERED]);

            // Обновляем связанные клиентские заказы
            $this->updateCustomerOrders($supplierOrder);

            // Здесь можно добавить интеграцию с API поставщика
            Log::info('Supplier order processed', [
                'supplier_order_id' => $supplierOrder->id,
                'supplier_id' => $supplierOrder->supplier_id,
                'total_quantity' => $supplierOrder->total_quantity,
                'customer_orders_count' => $supplierOrder->customer_orders_count
            ]);
        });
    }

    /**
     * Отменить заказ поставщика (недостаточно объема)
     */
    private function cancelSupplierOrder(SupplierOrder $supplierOrder): void
    {
        // Здесь можно отправить уведомления клиентам
        // или переместить их заказы в следующую поставку
        
        Log::warning('Supplier order cancelled - insufficient quantity', [
            'supplier_order_id' => $supplierOrder->id,
            'total_quantity' => $supplierOrder->total_quantity,
            'min_required' => $supplierOrder->supplier->min_order_quantity
        ]);

        // Пока просто удаляем - в будущем можно улучшить логику
        $supplierOrder->delete();
    }

    /**
     * Обновить клиентские заказы при подтверждении поставки
     */
    private function updateCustomerOrders(SupplierOrder $supplierOrder): void       //??
    {
        foreach ($supplierOrder->customerOrders as $customerOrder) {
            /** @var \Carbon\Carbon $deliveryDate */
            $deliveryDate = $supplierOrder->delivery_date;

            $customerOrder->update([
                'internal_notes' => ($customerOrder->internal_notes ?? '') . 
                    "\nЗаказан у поставщика, ожидаемая поставка: " . $deliveryDate->format('d.m.Y')
            ]);
        }
    }

    /**
     * Отметить поставку как выполненную
     */
    public function markAsDelivered(SupplierOrder $supplierOrder): void
    {
        DB::transaction(function () use ($supplierOrder) {
            $supplierOrder->update(['status' => SupplierOrder::STATUS_DELIVERED]);

            // Обновляем инвентарь
            foreach ($supplierOrder->items as $item) {
                // Добавляем товары на склад
                // Логика зависит от вашей структуры инвентаря
            }

            // Обновляем статусы клиентских заказов
            foreach ($supplierOrder->customerOrders as $customerOrder) {
                $customerOrder->update([
                    'internal_notes' => ($customerOrder->internal_notes ?? '') . 
                        "\nТовар получен от поставщика"
                ]);
            }
        });
    }
}