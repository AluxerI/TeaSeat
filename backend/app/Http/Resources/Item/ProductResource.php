<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\PriceCalculatorService;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        $data = $this->getAllData();
        
        $user = $request->user();
        $priceCalculator = app(PriceCalculatorService::class);
        $priceData = $priceCalculator->calculateForProduct($this->resource, $user);
        
        // Используем новый метод для получения объекта скидки
        $appliedDiscount = $priceCalculator->getAppliedDiscountObject($this->resource, $user);
        
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            
            'original_price' => $priceData['base_price'],
            'final_price' => $priceData['final_price'],
            'discount_percent' => $priceData['total_discount_percent'],
            
            'discount' => $appliedDiscount ? [
                'id' => $appliedDiscount->id,
                'name' => $appliedDiscount->name,
                'type' => $appliedDiscount->type,
                'value' => (float) $appliedDiscount->value,
                'code' => $appliedDiscount->code,
            ] : null,
            
            'weight_grams' => $this->weight_grams,
            'ingredients' => $this->ingredients,
            'description' => $this->description,
            
            'image' => $data['main_image_url'],
            'main_image' => $data['main_image_url'],
            'background_image' => $data['background_image_url'],
            'gallery' => $data['gallery_data'],
            
            'category_path' => $data['category_path'],
            
            'brand' => $this->brand?->name,
            'brand_id' => $this->brand?->id,
            
            'total_quantity' => $data['total_quantity'],
            'is_available' => $data['is_available'],
            'sold_count' => $data['sold_count'],
            
            'created_at' => $data['created_at'],
            'updated_at' => $this->updated_at?->format('d.m.Y H:i'),
        ];
    }
}