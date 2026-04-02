<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        // Используем кешированные данные из модели
        $data = $this->getAllData();
        
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'price' => $data['price'],
            'weight_grams' => $this->weight_grams,
            'ingredients' => $this->ingredients,
            'description' => $this->description,
            
            // Изображения
            'image' => $data['main_image_url'],
            'main_image' => $data['main_image_url'],
            'background_image' => $data['background_image_url'],
            'gallery' => $data['gallery_data'],
            
            // Категория
            'category_path' => $data['category_path'],
            
            // Бренд
            'brand' => $this->brand?->name,
            'brand_id' => $this->brand?->id,
            
            // Наличие
            'total_quantity' => $data['total_quantity'],
            'is_available' => $data['is_available'],
            'sold_count' => $data['sold_count'],
            
            // Даты
            'created_at' => $data['created_at'],
            'updated_at' => $this->updated_at?->format('d.m.Y H:i'),
        ];
    }
}