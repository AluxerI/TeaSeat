<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ManagerOrderAdjustmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'operation_id' => $this->operation_id,
            'order_id' => $this->order_id,
            'action' => $this->action,
            'order_product_id' => $this->order_product_id,
            'order_gift_id' => $this->order_gift_id,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ] : null),
            'fulfillment_issue_id' => $this->fulfillment_issue_id,
            'reason' => $this->reason,
            'before' => $this->before_snapshot,
            'after' => $this->after_snapshot,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
