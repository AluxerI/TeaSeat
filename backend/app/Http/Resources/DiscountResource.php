<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Item\ItemResource;

class DiscountResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'value' => (float) $this->value,
            'value_type' => $this->value_type,
            'type' => $this->type,
            'is_global' => $this->is_global,
            'min_order_amount' => $this->min_order_amount !== null
                ? (float) $this->min_order_amount
                : null,
            'usage_limit' => $this->usage_limit,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'pivot' => [
                'is_used' => (bool) ($this->pivot?->is_used ?? false),
                'used_count' => (int) ($this->pivot?->used_count ?? 0),
                'activated_at' => $this->pivot?->activated_at,
            ],
            'products' => $this->is_global ? [] : ItemResource::collection($this->whenLoaded('products')),
            'is_valid' => $this->isValid($request->user()),
            'days_remaining' => $this->end_date ? now()->diffInDays($this->end_date, false) : null,
            'remaining_uses' => $this->usage_limit
                ? max(0, $this->usage_limit - $this->used_count)
                : null,
            'can_be_applied' => $this->isValid($request->user()),
        ];
    }
}
