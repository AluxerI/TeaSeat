<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'order_id' => (int) $this->order_id,
            'type' => $this->type,
            'message' => $this->message,
            'status' => $this->status,
            'manager_comment' => $this->manager_comment,
            'manager' => $this->whenLoaded('manager', fn (): ?array => $this->manager ? [
                'id' => (int) $this->manager->id,
                'name' => $this->manager->name,
            ] : null),
            'can_withdraw' => $this->status === \App\Models\OrderRequest::STATUS_WAITING,
            'taken_at' => $this->taken_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
