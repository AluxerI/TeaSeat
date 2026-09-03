<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSizeResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->whenLoaded('product');
        $brand = $product ? $product->brand : null;
        return [
            'id' => $this->id,
            'label' => $this->label,
            'constructor_role' => $this->constructor_role,
            'product_quantity' => (int) $this->product_quantity,
            'packaging_template' => $this->whenLoaded(
                'packagingTemplate',
                fn (): ?array => $this->packagingTemplate?->is_active ? [
                    'id' => $this->packagingTemplate->id,
                    'code' => $this->packagingTemplate->code,
                    'name' => $this->packagingTemplate->name,
                    'kind' => $this->packagingTemplate->kind,
                    'image_url' => $this->packagingTemplate->image_url,
                ] : null,
                null
            ),
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'stock_unit' => $product->stockUnit(),
                'price_unit_quantity' => $product->priceUnitQuantity(),
                'image' => $product->getAllData()['main_image_url'] ?? null,
                'description' => $product->description,
                'ingredients' => $product->ingredients,
                'weight_grams' => $product->weight_grams,
                'assembly_instructions' => $product->assembly_instructions,
                'sold_count' => (int) $product->sold_count,
                'sku' => $product->sku,
                'brand' => $brand ? $brand->name : null,
                'total_quantity' => (int) $product->total_quantity,
            ] : null,
            'size' => new GiftSizeProfileResource($this->whenLoaded('sizeProfile')),
        ];
    }
}
