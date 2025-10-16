<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Item\ItemResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product' => new ItemResource($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            'prices' => [
                'unit_price' => $this->unit_price,
                'promotion_discount_percent' => $this->promotion_discount_percent,
                'personal_discount_percent' => $this->personal_discount_percent,
                'final_unit_price' => $this->final_unit_price,
                'total_price' => $this->total_price,
            ],
            'discounts' => [
                'promotion_discount_amount' => $this->quantity * ($this->unit_price * $this->promotion_discount_percent / 100),
                'personal_discount_amount' => $this->quantity * ($this->unit_price * $this->personal_discount_percent / 100),
            ]
        ];
    }
}