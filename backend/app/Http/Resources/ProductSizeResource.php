<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSizeResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->whenLoaded('product');
        return [
            'id' => $this->id,
            'label' => $this->label,
            'constructor_role' => $this->constructor_role,
            'product_quantity' => (int) $this->product_quantity,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'stock_unit' => $product->stockUnit(),
                'price_unit_quantity' => $product->priceUnitQuantity(),
                'image' => $product->getAllData()['main_image_url'] ?? null,
            ] : null,
            'size' => new GiftSizeProfileResource($this->whenLoaded('sizeProfile')),
        ];
    }
}
