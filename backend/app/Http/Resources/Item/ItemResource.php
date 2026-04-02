<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request)
    {
        // Используем кешированные детальные данные из модели
        $data = $this->getDetailedData();
        
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'price' => $data['price'],
            'weight_grams' => $data['weight_grams'],
            'ingredients' => $data['ingredients'],
            'description' => $data['description'],
            
            // Изображения
            'image' => $data['main_image_url'],
            'main_image' => $data['main_image_url'],
            'background_image' => $data['background_image_url'],
            'gallery' => $data['gallery_data'],
            
            // Категория
            'category_path' => $data['category_path'],
            
            // Бренд
            'brand' => $data['brand_name'],
            'brand_id' => $this->brand?->id,
            
            // Наличие на складах (детально)
            'inventory' => $data['inventory'] ?? [],
            'total_quantity' => $data['total_quantity'],
            'is_available' => $data['is_available'],
            'sold_count' => $data['sold_count'],
            
            // Акции и скидки (если есть в getDetailedData)
            'promotions' => $data['promotions'] ?? [],
            'discounts' => $data['discounts'] ?? [],
            
            // Даты
            'created_at' => $data['created_at'],
            'updated_at' => $this->updated_at?->format('d.m.Y H:i'),
        ];
    }
}