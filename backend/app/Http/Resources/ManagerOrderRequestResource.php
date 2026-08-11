<?php

namespace App\Http\Resources;

use App\Models\OrderRequest;
use App\Models\User;

class ManagerOrderRequestResource extends OrderRequestResource
{
    public function toArray($request): array
    {
        $actor = $request->user();
        $owns = $actor && (int) $this->manager_id === (int) $actor->id;
        $canManageOwned = $owns || ($actor?->hasRole(User::ROLE_ADMIN) ?? false);

        return parent::toArray($request) + [
            'customer' => $this->whenLoaded('user', fn (): array => [
                'id' => (int) $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),
            'order' => $this->whenLoaded('order', fn (): array => [
                'id' => (int) $this->order->id,
                'order_number' => $this->order->order_number,
                'status' => $this->order->status,
                'warehouse' => $this->order->relationLoaded('warehouse') && $this->order->warehouse
                    ? [
                        'id' => (int) $this->order->warehouse->id,
                        'name' => $this->order->warehouse->name,
                    ]
                    : null,
                'destination_warehouse' => $this->order->relationLoaded('destinationWarehouse')
                    && $this->order->destinationWarehouse
                    ? [
                        'id' => (int) $this->order->destinationWarehouse->id,
                        'name' => $this->order->destinationWarehouse->name,
                    ]
                    : null,
                'created_at' => $this->order->created_at?->toIso8601String(),
            ]),
            'actions' => [
                'can_take' => $this->status === OrderRequest::STATUS_WAITING,
                'can_release' => $this->status === OrderRequest::STATUS_IN_REVIEW
                    && $canManageOwned,
                'can_resolve' => $this->status === OrderRequest::STATUS_IN_REVIEW
                    && $canManageOwned,
                'can_reject' => $this->status === OrderRequest::STATUS_IN_REVIEW
                    && $canManageOwned,
            ],
        ];
    }
}
