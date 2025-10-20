<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'summary' => [
                'products_total' => $this->products_total,
                'promotion_discount' => $this->promotion_discount,
                'personal_discount' => $this->personal_discount,
                'cart_discount' => $this->cart_discount,
                'shipping_cost' => $this->shipping_cost,
                'final_total' => $this->final_total,
                'items_count' => $this->items->sum('quantity'),
                'unique_items_count' => $this->items->count(),
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shipping_address' => new AddressClientResource($this->whenLoaded('shippingAddress')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}