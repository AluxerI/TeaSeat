<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderGiftResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'gift_id' => $this->gift_id,
            'client_instance_id' => $this->client_instance_id,
            'gift_version' => (int) $this->gift_version,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => (int) $this->quantity,
            'layout' => $this->layout_snapshot,
            'prices' => [
                'markup_unit_amount' => (float) $this->markup_unit_amount,
                'markup_total_amount' => (float) $this->markup_total_amount,
                'components_base_total' => (float) $this->components_base_total,
                'components_discount_amount' => (float) $this->components_discount_amount,
                'total_price' => (float) $this->total_price,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
