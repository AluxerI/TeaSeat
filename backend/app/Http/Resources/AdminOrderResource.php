<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'order_type' => $this->is_supplier_order ? 'supplier' : 'regular',
            'order_type_name' => $this->is_supplier_order ? 'Заказ у поставщика' : 'Обычный заказ',
            
            // Финансы
            'totals' => [
                'products_total' => $this->products_total,
                'promotion_discount' => $this->promotion_discount,
                'personal_discount' => $this->personal_discount,
                'shipping_cost' => $this->shipping_cost,
                'final_total' => $this->final_total,
            ],
            
            // Информация о клиенте (простая, без отдельного ресурса)
            'customer' => $this->whenLoaded('user', function() {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ];
            }),
            
            // Доставка
            'delivery' => [
                'method' => $this->whenLoaded('deliveryMethod', function() {
                    return [
                        'id' => $this->deliveryMethod->id,
                        'name' => $this->deliveryMethod->name,
                        'cost' => $this->deliveryMethod->cost,
                    ];
                }),
                'address' => $this->whenLoaded('shippingAddress', function() {
                    return [
                        'id' => $this->shippingAddress->id,
                        'street' => $this->shippingAddress->street,
                        'city' => $this->shippingAddress->city,
                        'postal_code' => $this->shippingAddress->postal_code,
                        'full_address' => $this->shippingAddress->getFullAddress(),
                    ];
                }),
                'tracking_number' => $this->tracking_number,
                'warehouse' => $this->whenLoaded('warehouse', function() {
                    return [
                        'id' => $this->warehouse->id,
                        'name' => $this->warehouse->name,
                        'city' => $this->warehouse->city,
                    ];
                }),
            ],
            
            // Товары
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            
            // Частичные заказы (простая версия без рекурсии)
            'partial_orders' => $this->whenLoaded('partialOrders', function() {
                return $this->partialOrders->map(function($partialOrder) {
                    return [
                        'id' => $partialOrder->id,
                        'status' => $partialOrder->status,
                        'warehouse_id' => $partialOrder->warehouse_id,
                        'items_count' => $partialOrder->items->count(),
                    ];
                });
            }),
            
            // Заказ у поставщика
            'supplier_order' => $this->when($this->is_supplier_order && $this->relationLoaded('supplierOrder'), function() {
                return [
                    'supplier' => $this->supplierOrder->supplier->name ?? 'Не указан',
                    'scheduled_date' => $this->supplierOrder->scheduled_date ?? null,
                    'status' => $this->supplierOrder->status ?? null,
                ];
            }),
            
            // Внутренняя информация
            'internal_info' => [
                'internal_notes' => $this->internal_notes,
                'customer_notes' => $this->customer_notes,
                'is_supplier_order' => $this->is_supplier_order,
            ],
            
            // Временные метки
            'timestamps' => [
                'created_at' => $this->created_at,
                'confirmed_at' => $this->confirmed_at,
                'shipped_at' => $this->shipped_at,
                'delivered_at' => $this->delivered_at,
                'cancelled_at' => $this->cancelled_at,
            ],
            
            // История статусов (простая версия)
            'status_history' => $this->whenLoaded('statusHistory', function() {
                return $this->statusHistory->map(function($history) {
                    return [
                        'from_status' => $history->from_status,
                        'to_status' => $history->to_status,
                        'changed_by' => $history->changed_by,
                        'notes' => $history->notes,
                        'created_at' => $history->created_at,
                    ];
                });
            }),
        ];
    }
}