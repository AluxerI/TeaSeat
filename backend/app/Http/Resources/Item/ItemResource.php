<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\PriceCalculatorService;
use App\Models\Product;

class ItemResource extends JsonResource
{
    public function toArray($request)
    {
        $data = $this->getDetailedData();
        
        $user = $request->user();
        $priceCalculator = app(PriceCalculatorService::class);
        $priceData = $priceCalculator->calculateForProduct($this->resource, $user);
        
        $appliedDiscount = $priceCalculator->getAppliedDiscountObject($this->resource, $user);
        $allDiscounts = $priceCalculator->getAllApplicableDiscounts($this->resource, $user);
        
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'product_type' => $data['product_type'] ?? Product::TYPE_REGULAR,
            
            'original_price' => $priceData['base_price'],
            'final_price' => $priceData['final_price'],
            'discount_percent' => $priceData['total_discount_percent'],
            'discount_saved' => round($priceData['base_price'] - $priceData['final_price'], 2),
            
            'discount' => $appliedDiscount ? [
                'id' => $appliedDiscount->id,
                'name' => $appliedDiscount->name,
                'description' => $appliedDiscount->description,
                'type' => $appliedDiscount->type,
                'value' => (float) $appliedDiscount->value,
                'value_type' => $appliedDiscount->value_type,
                'code' => $appliedDiscount->code,
            ] : null,
            
            'available_discounts' => $allDiscounts->map(function($discount) {
                return [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'type' => $discount->type,
                    'value' => (float) $discount->value,
                    'value_type' => $discount->value_type,
                    'description' => $discount->description,
                    'code' => $discount->code,
                ];
            })->values()->toArray(),
            
            'weight_grams' => $data['weight_grams'],
            'stock_unit' => $data['stock_unit'],
            'sale_step' => $data['sale_step'],
            'price_unit_quantity' => $data['price_unit_quantity'],
            'ingredients' => $data['ingredients'],
            'description' => $data['description'],
            
            'image' => $data['main_image_url'],
            'main_image' => $data['main_image_url'],
            'background_image' => $data['background_image_url'],
            'gallery' => $data['gallery_data'],
            
            'category_path' => $data['category_path'],
            
            'brand' => $data['brand_name'],
            'brand_id' => $this->brand?->id,
            
            'inventory' => $data['inventory'] ?? [],
            'total_quantity' => $data['total_quantity'],
            'is_available' => $data['is_available'],
            'sold_count' => $data['sold_count'],
            
            'created_at' => $data['created_at'],
            'updated_at' => $this->updated_at?->format('d.m.Y H:i'),
        ];
    }
}
