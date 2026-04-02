<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Order;

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
                'products_total' => (float) $this->products_total,
                'promotion_discount' => (float) ($this->promotion_discount ?? 0),
                'personal_discount' => (float) ($this->personal_discount ?? 0),
                'shipping_cost' => (float) $this->shipping_cost,
                'final_total' => (float) $this->final_total,
            ],
            
            // Информация о клиенте
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
                        'cost' => (float) $this->deliveryMethod->cost,
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
            
            // Частичные заказы
            'partial_orders' => $this->whenLoaded('partialOrders', function() {
                return $this->partialOrders->map(function($partialOrder) {
                    return [
                        'id' => $partialOrder->id,
                        'order_number' => $partialOrder->order_number,
                        'status' => $partialOrder->status,
                        'warehouse_id' => $partialOrder->warehouse_id,
                        'items_count' => $partialOrder->items->count(),
                        'final_total' => (float) $partialOrder->final_total,
                    ];
                });
            }),
            
            // Заказ у поставщика
            'supplier_order' => $this->when($this->is_supplier_order && $this->relationLoaded('supplierOrder'), function() {
                return [
                    'id' => $this->supplierOrder?->id,
                    'supplier_name' => $this->supplierOrder?->supplier?->name,
                    'scheduled_date' => $this->supplierOrder?->scheduled_date?->format('d.m.Y'),
                    'status' => $this->supplierOrder?->status,
                ];
            }),
            
            // Внутренняя информация
            'internal_info' => [
                'internal_notes' => $this->internal_notes,
                'customer_notes' => $this->customer_notes,
                'is_supplier_order' => $this->is_supplier_order ?? false,
                'payment_method' => $this->payment_method,
            ],
            
            // Временные метки
            'timestamps' => [
                'created_at' => $this->created_at?->format('d.m.Y H:i'),
                'confirmed_at' => $this->confirmed_at?->format('d.m.Y H:i'),
                'shipped_at' => $this->shipped_at?->format('d.m.Y H:i'),
                'delivered_at' => $this->delivered_at?->format('d.m.Y H:i'),
                'cancelled_at' => $this->cancelled_at?->format('d.m.Y H:i'),
            ],
            
            // История статусов
            'status_history' => $this->whenLoaded('statusHistory', function() {
                return $this->statusHistory->map(function($history) {
                    return [
                        'from_status' => $history->from_status,
                        'from_status_name' => Order::getStatusName($history->from_status),
                        'to_status' => $history->to_status,
                        'to_status_name' => Order::getStatusName($history->to_status),
                        'changed_by' => $history->changed_by,
                        'changed_by_name' => $history->changedBy?->name,
                        'notes' => $history->notes,
                        'created_at' => $history->created_at?->format('d.m.Y H:i'),
                    ];
                });
            }),
        ];
    }
}