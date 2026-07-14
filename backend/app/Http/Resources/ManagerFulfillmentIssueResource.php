<?php

namespace App\Http\Resources;

use App\Models\FulfillmentIssue;
use App\Models\User;

class ManagerFulfillmentIssueResource extends FulfillmentIssueResource
{
    public function toArray($request): array
    {
        $user = $request->user();
        $ownerOrAdmin = $user
            && ((int) $this->manager_id === (int) $user->id
                || $user->hasRole(User::ROLE_ADMIN));

        return parent::toArray($request) + [
            'status_name' => match ($this->status) {
                FulfillmentIssue::STATUS_WAITING => 'Ожидает менеджера',
                FulfillmentIssue::STATUS_IN_REVIEW => 'На рассмотрении',
                FulfillmentIssue::STATUS_CLOSED => 'Закрыто без признака решения',
                default => $this->status,
            },
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
                'email' => $this->manager->email,
                'phone' => $this->manager->phone,
            ] : null),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'stock_unit' => $this->product->stockUnit(),
                'sale_step' => $this->product->saleStep(),
                'price_unit_quantity' => $this->product->priceUnitQuantity(),
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'city' => $this->warehouse->city,
                'type' => $this->warehouse->type,
                'is_active' => (bool) $this->warehouse->is_active,
            ]),
            'source_order' => $this->whenLoaded('sourceOrder', function (): array {
                return [
                    'id' => $this->sourceOrder->id,
                    'order_number' => $this->sourceOrder->order_number,
                    'status' => $this->sourceOrder->status,
                    'sales_channel' => $this->sourceOrder->sales_channel,
                    'revision' => (int) $this->sourceOrder->seller_revision,
                    'was_edited' => (bool) $this->sourceOrder->was_edited,
                    'final_total' => (float) $this->sourceOrder->final_total,
                    'seller' => $this->sourceOrder->relationLoaded('user') ? [
                        'id' => $this->sourceOrder->user?->id,
                        'name' => $this->sourceOrder->user?->name,
                        'email' => $this->sourceOrder->user?->email,
                        'phone' => $this->sourceOrder->user?->phone,
                    ] : null,
                    'device' => $this->sourceOrder->relationLoaded('sellerDevice')
                        && $this->sourceOrder->sellerDevice ? [
                            'id' => $this->sourceOrder->sellerDevice->id,
                            'device_uuid' => $this->sourceOrder->sellerDevice->device_uuid,
                            'name' => $this->sourceOrder->sellerDevice->name,
                        ] : null,
                    'items' => $this->sourceOrder->relationLoaded('items')
                        ? $this->sourceOrder->items->map(fn ($item): array => [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product?->name,
                            'quantity' => (int) $item->quantity,
                            'stock_unit' => $item->stock_unit,
                            'total_price' => (float) $item->total_price,
                        ])->values()->all()
                        : [],
                    'occurred_at' => $this->sourceOrder->seller_occurred_at?->toIso8601String(),
                    'synced_at' => $this->sourceOrder->seller_synced_at?->toIso8601String(),
                    'escalated_at' => $this->sourceOrder->seller_escalated_at?->toIso8601String(),
                ];
            }),
            'actions' => [
                'can_take' => $this->status === FulfillmentIssue::STATUS_WAITING,
                'can_release' => $this->status === FulfillmentIssue::STATUS_IN_REVIEW
                    && $ownerOrAdmin,
                'can_close' => $this->status === FulfillmentIssue::STATUS_IN_REVIEW
                    && $ownerOrAdmin,
                'can_reopen' => false,
            ],
        ];
    }
}
