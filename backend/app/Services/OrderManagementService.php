<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderManagementService
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    /**
     * Получить заказы с фильтрами
     */
    public function getOrdersWithFilters(Request $request): LengthAwarePaginator
    {
        $query = Order::with(['user', 'deliveryMethod', 'shippingAddress'])
            ->realOrders()
            ->whereNull('parent_order_id')
            ->latest();

        // Фильтр по статусу
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Фильтр по типу заказа
        if ($request->has('order_type')) {
            if ($request->order_type === 'supplier') {
                $query->where('is_supplier_order', true);
            } elseif ($request->order_type === 'regular') {
                $query->where('is_supplier_order', false);
            }
        }

        // Фильтр по дате
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Поиск по номеру заказа или email пользователя
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('email', 'LIKE', "%{$search}%")
                               ->orWhere('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        return $query->paginate($request->get('per_page', 20));
    }

    /**
     * Обновить статус заказа
     */
    public function updateOrderStatus(Order $order, string $status, ?string $notes = null, ?int $managerId = null): Order
    {
        return DB::transaction(function () use ($order, $status, $notes, $managerId) {
            $oldStatus = $order->status;
            
            $order->update(['status' => $status]);
        
            // Логируем смену статуса
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $oldStatus,
                'to_status' => $status,
                'changed_by' => $managerId, // ← используем переданный ID
                'notes' => $notes
            ]);
        
            // Обновляем internal_notes если есть
            if ($notes) {
                $order->update([
                    'internal_notes' => ($order->internal_notes ?? '') . "\n" . now()->format('d.m.Y H:i') . ": " . $notes
                ]);
            }
        
            $this->updateStatusTimestamps($order, $status);
        
            return $order->fresh();
        });
    }

    /**
     * Обновить трек-номер
     */
    public function updateTrackingNumber(Order $order, string $trackingNumber, ?string $carrier = null): Order
    {
        $order->update([
            'tracking_number' => $trackingNumber,
            'internal_notes' => ($order->internal_notes ?? '') . 
                "\nТрек-номер обновлен: {$trackingNumber}" . 
                ($carrier ? " ({$carrier})" : '')
        ]);

        return $order->fresh();
    }

    /**
     * Отменить заказ менеджером
     */
    public function cancelOrderByManager(Order $order, ?string $reason, int $managerId): Order
    {
        return $this->checkoutService->cancelOrderByManager(
            $order->id,
            $managerId,
            $reason
        );
    }

    /**
     * Подтвердить заказ
     */
    public function confirmOrder(Order $order, int $managerId): Order
    {
        return $this->updateOrderStatus(
            $order, 
            Order::STATUS_CONFIRMED, 
            "Подтвержден менеджером ID: {$managerId}"
        );
    }

    /**
     * Отметить как отправленный
     */
    public function markAsShipped(Order $order, int $managerId): Order
    {
        return $this->updateOrderStatus(
            $order, 
            Order::STATUS_SHIPPED, 
            "Отмечен как отправленный менеджером ID: {$managerId}"
        );
    }

    /**
     * Отметить как доставленный
     */
    public function markAsDelivered(Order $order, int $managerId): Order
    {
        return $this->updateOrderStatus(
            $order, 
            Order::STATUS_DELIVERED, 
            "Отмечен как доставленный менеджером ID: {$managerId}"
        );
    }

    /**
     * Обновить способ доставки
     */
    public function updateDeliveryMethod(Order $order, int $deliveryMethodId, ?float $shippingCost = null): Order
    {
        $deliveryMethod = \App\Models\DeliveryMethod::findOrFail($deliveryMethodId);
        
        $order->update([
            'delivery_method_id' => $deliveryMethodId,
            'shipping_cost' => $shippingCost ?? $deliveryMethod->cost,
            'internal_notes' => ($order->internal_notes ?? '') . 
                "\nСпособ доставки изменен на: {$deliveryMethod->name}"
        ]);

        // Пересчитываем итог
        $this->recalculateOrderTotal($order);

        return $order->fresh();
    }

    /**
     * Получить статистику заказов
     */
    public function getOrderStats(Request $request): array
    {
        $query = Order::realOrders()->whereNull('parent_order_id');

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return [
            'total_orders' => $query->count(),
            'total_revenue' => $query->sum('final_total'),
            'status_stats' => $query->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'average_order_value' => $query->avg('final_total'),
            'pending_orders' => $query->where('status', Order::STATUS_PENDING)->count(),
            'supplier_orders' => $query->where('is_supplier_order', true)->count(),
        ];
    }

    /**
     * Обновить временные метки статусов
     */
    private function updateStatusTimestamps(Order $order, string $status): void
    {
        $updates = [];
        
        switch ($status) {
            case Order::STATUS_CONFIRMED:
                $updates['confirmed_at'] = now();
                break;
            case Order::STATUS_SHIPPED:
                $updates['shipped_at'] = now();
                break;
            case Order::STATUS_DELIVERED:
                $updates['delivered_at'] = now();
                break;
            case Order::STATUS_CANCELLED:
                $updates['cancelled_at'] = now();
                break;
        }

        if (!empty($updates)) {
            $order->update($updates);
        }
    }

    /**
     * Пересчитать итоговую сумму
     */
    private function recalculateOrderTotal(Order $order): void
    {
        $productsTotal = $order->items->sum('total_price');
        $shippingCost = $order->shipping_cost;
        
        $order->update([
            'products_total' => $productsTotal,
            'final_total' => $productsTotal + $shippingCost
        ]);
    }
}
