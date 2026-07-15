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
            'order_gift_id' => $this->order_gift_id,
            'gift_item_client_id' => $this->gift_item_client_id,
            'gift_item_quantity' => $this->gift_item_quantity !== null
                ? (int) $this->gift_item_quantity
                : null,
            'gift_item_sort_order' => $this->gift_item_sort_order !== null
                ? (int) $this->gift_item_sort_order
                : null,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'stock_unit' => $this->stock_unit ?: $product->stockUnit(),
                'sale_step' => (int) ($this->sale_step ?: $product->saleStep()),
                'price_unit_quantity' => (int) ($this->price_unit_quantity ?: $product->priceUnitQuantity()),
                'image' => $productData['main_image_url'] ?? $product->main_image_url,
                'weight_grams' => $product->weight_grams,
                'category_path' => $productData['category_path'] ?? null,
            ] : null,
            'quantity' => $this->quantity,
            'stock_unit' => $this->stock_unit ?: $product?->stockUnit(),
            'sale_step' => (int) ($this->sale_step ?: $product?->saleStep() ?: 1),
            'price_unit_quantity' => (int) ($this->price_unit_quantity ?: $product?->priceUnitQuantity() ?: 1),
            'prices' => [
                'unit_price' => (float) $this->unit_price,
                'base_total' => (float) ($this->pricing_snapshot['base_total'] ?? round(
                    (float) $this->unit_price * $this->quantity / max(1, (int) $this->price_unit_quantity),
                    2
                )),
                'promotion_discount_percent' => (float) ($this->promotion_discount_percent ?? 0),
                'personal_discount_percent' => (float) ($this->personal_discount_percent ?? 0),
                'promotion_discount_amount' => (float) ($this->promotion_discount_amount ?? 0),
                'selected_discount_amount' => (float) ($this->selected_discount_amount ?? 0),
                'final_unit_price' => (float) $this->final_unit_price,
                'total_price' => (float) $this->total_price,
            ],
            'discounts' => [
                'promotion' => $this->pricing_snapshot['promotion'] ?? null,
                'selected' => $this->pricing_snapshot['selected_discount'] ?? null,
                'promotion_discount_amount' => (float) ($this->promotion_discount_amount ?? 0),
                'selected_discount_amount' => (float) ($this->selected_discount_amount ?? 0),
            ]
        ];
    }
}
