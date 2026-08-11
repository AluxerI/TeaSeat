<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderFeedbackResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'order_id' => (int) $this->order_id,
            'ratings' => [
                'delivery' => $this->delivery_rating,
                'packing' => $this->packing_rating,
                'service' => $this->service_rating,
            ],
            'comment' => $this->comment,
            'status' => $this->status,
            'customer_edited_at' => $this->customer_edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
