<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Resources\Json\JsonResource;

class PickerOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $customerOrder = $this->parentOrder ?: $this->resource;
        $user = $request->user();
        $isOwner = $user && (int) $this->picker_id === (int) $user->id;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_order_id' => $customerOrder->id,
            'customer_order_number' => $customerOrder->order_number,
            'job_type' => $this->parent_order_id ? 'source' : 'consolidation',
            'status' => $this->status,
            'status_name' => $this->status_name,
            'warehouse' => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'city' => $this->warehouse->city,
                'location' => $this->warehouse->location,
            ] : null,
            'destination_warehouse' => $this->destinationWarehouse ? [
                'id' => $this->destinationWarehouse->id,
                'name' => $this->destinationWarehouse->name,
                'city' => $this->destinationWarehouse->city,
                'location' => $this->destinationWarehouse->location,
            ] : null,
            'picker' => $this->picker ? [
                'id' => $this->picker->id,
                'name' => $this->picker->name,
            ] : null,
            'items' => $this->items->map(fn ($item): array => [
                'order_product_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'order_gift_id' => $item->order_gift_id,
                'gift_name' => $item->orderGift?->name,
                'gift_item_client_id' => $item->gift_item_client_id,
                'gift_item_quantity' => $item->gift_item_quantity !== null
                    ? (int) $item->gift_item_quantity
                    : null,
                'quantity' => (int) $item->quantity,
                'stock_unit' => $item->stock_unit,
                'sale_step' => (int) $item->sale_step,
            ])->values(),
            'gifts' => $this->items
                ->whereNotNull('order_gift_id')
                ->groupBy('order_gift_id')
                ->map(function ($items, $orderGiftId): array {
                    $gift = $items->first()->orderGift;
                    return [
                        'order_gift_id' => (int) $orderGiftId,
                        'name' => $gift?->name,
                        'quantity' => (int) ($gift?->quantity ?? 1),
                        'layout' => $gift?->layout_snapshot,
                        'components' => $items->map(fn ($item): array => [
                            'order_product_id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product?->name,
                            'quantity' => (int) $item->quantity,
                            'stock_unit' => $item->stock_unit,
                            'gift_item_client_id' => $item->gift_item_client_id,
                        ])->values(),
                    ];
                })->values(),
            'customer_notes' => $customerOrder->customer_notes,
            'timestamps' => [
                'created_at' => $this->created_at?->toIso8601String(),
                'picking_started_at' => $this->picking_started_at?->toIso8601String(),
                'ready_for_delivery_at' => $this->ready_for_delivery_at?->toIso8601String(),
                'courier_arrived_at' => $this->courier_arrived_at?->toIso8601String(),
                'received_at' => $this->received_at?->toIso8601String(),
            ],
            'actions' => [
                'can_take' => $this->status === Order::STATUS_CONFIRMED
                    && $this->picker_id === null,
                'can_release' => $this->status === Order::STATUS_PROCESSING
                    && $isOwner,
                'can_complete' => $this->status === Order::STATUS_PROCESSING
                    && $isOwner,
                'can_escalate' => $this->status === Order::STATUS_PROCESSING
                    && $isOwner,
                'can_report_shortage' => $this->parent_order_id !== null
                    && $this->status === Order::STATUS_PROCESSING
                    && $isOwner,
                'can_receive' => $this->status === Order::STATUS_AWAITING_RECEIPT,
            ],
        ];
    }
}
