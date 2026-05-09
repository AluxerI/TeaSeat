<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request)
    {
        $product = $this->whenLoaded('product');
        
        // Используем кешированные данные из модели Product
        $productData = $product ? $product->getAllData() : null;
        
        return [
            'id' => $this->id,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image' => $productData['main_image_url'] ?? $product->main_image_url,
                'weight_grams' => $product->weight_grams,
                'category_path' => $productData['category_path'] ?? null,
            ] : null,
            'quantity' => $this->quantity,
            'prices' => [
                'unit_price' => (float) $this->unit_price,
                'promotion_discount_percent' => (float) ($this->promotion_discount_percent ?? 0),
                'personal_discount_percent' => (float) ($this->personal_discount_percent ?? 0),
                'final_unit_price' => (float) $this->final_unit_price,
                'total_price' => (float) $this->total_price,
            ],
            'discounts' => [
                'promotion_discount_amount' => (float) ($this->quantity * ($this->unit_price * ($this->promotion_discount_percent ?? 0) / 100)),
                'personal_discount_amount' => (float) ($this->quantity * ($this->unit_price * ($this->personal_discount_percent ?? 0) / 100)),
            ]
        ];
    }
}