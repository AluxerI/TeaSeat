<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'client_order_id' => $this->client_order_id,
            'revision' => (int) $this->seller_revision,
            'sales_channel' => $this->sales_channel,
            'status' => $this->status,
            'status_name' => Order::getStatusName($this->status),
            'payment_method' => $this->payment_method,
            'was_edited' => (bool) $this->was_edited,
            'actions' => [
                'can_edit' => in_array($this->status, [
                    Order::STATUS_PENDING,
                    Order::STATUS_SELLER_REVIEW,
                ], true),
                'can_cancel' => in_array($this->status, [
                    Order::STATUS_PENDING,
                    Order::STATUS_SELLER_REVIEW,
                ], true),
                'can_complete' => in_array($this->status, [
                    Order::STATUS_PENDING,
                    Order::STATUS_SELLER_REVIEW,
                ], true),
                'can_escalate' => $this->status === Order::STATUS_SELLER_REVIEW,
            ],
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'city' => $this->warehouse->city,
                'type' => $this->warehouse->type,
            ]),
            'device' => new StaffDeviceResource($this->whenLoaded('sellerDevice')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'totals' => [
                'products_total' => (float) $this->products_total,
                'promotion_discount' => (float) $this->promotion_discount,
                'final_total' => (float) $this->final_total,
                'currency' => $this->pricing_snapshot['currency'] ?? 'RUB',
            ],
            'fulfillment_issues' => FulfillmentIssueResource::collection(
                $this->whenLoaded('fulfillmentIssues')
            ),
            'timestamps' => [
                'occurred_at' => $this->seller_occurred_at?->toIso8601String(),
                'synced_at' => $this->seller_synced_at?->toIso8601String(),
                'reviewed_at' => $this->seller_reviewed_at?->toIso8601String(),
                'escalated_at' => $this->seller_escalated_at?->toIso8601String(),
                'completed_at' => $this->seller_completed_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
        ];
    }
}
