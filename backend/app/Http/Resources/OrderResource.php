<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'order_number' => $this->generateOrderNumber(),
            
            'is_partial' => !is_null($this->parent_order_id),
            'parent_order_id' => $this->parent_order_id,

            // 🎯 ИНФОРМАЦИЯ О ТИПЕ ЗАКАЗА
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
                'products_total' => $this->products_total,
                'promotion_discount' => $this->promotion_discount,
                'personal_discount' => $this->personal_discount,
                'cart_discount' => $this->cart_discount,
                'shipping_cost' => $this->shipping_cost,
                'final_total' => $this->final_total,
            ],

            'delivery_info' => [
                'estimated_days' => $this->deliveryMethod->getEstimatedDaysFormatted() ?? 'уточняется',
                'has_multiple_warehouses' => $this->partialOrders->isNotEmpty(),
                'warehouse_count' => $this->partialOrders->count() + 1, // основной + частичные
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
            
            // Товары в заказе
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            
            // Дополнительная информация
            'customer_notes' => $this->customer_notes,
            'timestamps' => [
                'created_at' => $this->created_at,
                'confirmed_at' => $this->confirmed_at,
                'paid_at' => $this->paid_at,
                'shipped_at' => $this->shipped_at,
                'delivered_at' => $this->delivered_at,
                'cancelled_at' => $this->cancelled_at,
            ],
            
            // Статус заказа
            'status_info' => $this->getStatusInfo(),
        ];
    }

    /**
     * Генерация номера заказа
     */
    private function generateOrderNumber(): string
    {
        return 'TE-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
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