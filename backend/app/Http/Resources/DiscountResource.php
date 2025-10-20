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
            'value' => $this->value,
            'type' => $this->type,
            'is_global' => $this->is_global,
            'min_order_amount' => $this->min_order_amount,
            'usage_limit' => $this->usage_limit,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'pivot' => [
                'is_used' => $this->pivot->is_used,
                'used_count' => $this->pivot->used_count,
                'activated_at' => $this->pivot->activated_at,
            ],
            'products' => $this->is_global ? [] : ItemResource::collection($this->whenLoaded('products')),
            'is_valid' => $this->isValid(),
            'days_remaining' => $this->end_at ? now()->diffInDays($this->end_at, false) : null,
            'remaining_uses' => $this->remaining_uses,
            'can_be_applied' => $this->can_be_applied,
        ];
    }
}