<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GiftResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'version' => (int) $this->version,
            'markup_amount' => (float) $this->markup_amount,
            'box' => new GiftSizeProfileResource($this->whenLoaded('sizeProfile')),
            'layout' => $this->layout_snapshot,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'client_item_id' => $item->client_item_id,
                'product_size' => new ProductSizeResource($item->productSize),
                'position_x' => (int) $item->position_x,
                'position_y' => (int) $item->position_y,
                'is_rotated' => (bool) $item->is_rotated,
                'sort_order' => (int) $item->sort_order,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
