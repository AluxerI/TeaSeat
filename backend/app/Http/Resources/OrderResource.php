<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Item\ItemResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'sales_channel' => $this->sales_channel,
            'payment_method' => $this->payment_method,
            'order_number' => $this->order_number,
            
            'is_partial' => !is_null($this->parent_order_id),
            'parent_order_id' => $this->parent_order_id,

            // Информация о типе заказа
            'order_type' => $this->is_supplier_order ? 'supplier' : 'regular',
            'order_type_name' => $this->is_supplier_order ? 'Заказ у поставщика' : 'Обычный заказ',
            
            // Для заказов у поставщика - дополнительная информация
            'supplier_info' => $this->when($this->is_supplier_order, function() {
                return [
                    'status' => 'pending',
                    'message' => 'Заказ передан поставщику. Мы свяжемся с вами для уточнения сроков доставки.',
                    'estimated_processing' => '1-3 рабочих дня'
                ];
            }),

            // Суммы
            'totals' => [
                'products_total' => (float) $this->products_total,
                'promotion_discount' => (float) ($this->promotion_discount ?? 0),
                'personal_discount' => (float) ($this->personal_discount ?? 0),
                'cart_discount' => (float) ($this->cart_discount ?? 0),
                'shipping_cost' => (float) $this->shipping_cost,
                'shipping_discount' => (float) ($this->shipping_discount ?? 0),
                'final_total' => (float) $this->final_total,
            ],
            'selected_discount' => $this->pricing_snapshot['selected_discount'] ?? null,

            'delivery_info' => [
                'estimated_days' => $this->deliveryMethod?->getEstimatedDaysFormatted() ?? 'уточняется',
                'has_multiple_warehouses' => $this->partialOrders->count() > 1,
                'warehouse_count' => $this->partialOrders->count(),
            ],
            
            // Информация о доставке
            'delivery' => [
                'method' => new DeliveryMethodResource($this->whenLoaded('deliveryMethod')),
                'address' => new AddressClientResource($this->whenLoaded('shippingAddress')),
                'warehouse' => $this->whenLoaded('warehouse', function() {
                    return [
                        'id' => $this->warehouse->id,
                        'name' => $this->warehouse->name,
                        'city' => $this->warehouse->city,
                    ];
                }),
                'tracking_number' => $this->tracking_number,
            ],
            
            // Товары в заказе (используем кешированные данные)
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            
            // Дополнительная информация
            'customer_notes' => $this->customer_notes,
            'timestamps' => [
                'created_at' => $this->created_at?->format('d.m.Y H:i'),
                'confirmed_at' => $this->confirmed_at?->format('d.m.Y H:i'),
                'paid_at' => $this->paid_at?->format('d.m.Y H:i'),
                'shipped_at' => $this->shipped_at?->format('d.m.Y H:i'),
                'delivered_at' => $this->delivered_at?->format('d.m.Y H:i'),
                'cancelled_at' => $this->cancelled_at?->format('d.m.Y H:i'),
                'stock_reserved_at' => $this->stock_reserved_at?->format('d.m.Y H:i'),
                'stock_committed_at' => $this->stock_committed_at?->format('d.m.Y H:i'),
                'stock_released_at' => $this->stock_released_at?->format('d.m.Y H:i'),
            ],
            
            // Статус заказа
            'status_info' => $this->getStatusInfo(),
        ];
    }

    /**
     * Информация о статусе заказа
     */
    private function getStatusInfo(): array
    {
        $statuses = [
            'cart' => ['name' => 'Корзина', 'color' => 'gray'],
            'pending' => ['name' => 'Ожидает подтверждения', 'color' => 'yellow'],
            'confirmed' => ['name' => 'Подтвержден', 'color' => 'blue'],
            'processing' => ['name' => 'Обрабатывается', 'color' => 'indigo'],
            'shipped' => ['name' => 'Отправлен', 'color' => 'purple'],
            'delivered' => ['name' => 'Доставлен', 'color' => 'green'],
            'cancelled' => ['name' => 'Отменен', 'color' => 'red'],
        ];

        return $statuses[$this->status] ?? ['name' => 'Неизвестно', 'color' => 'gray'];
    }
}
