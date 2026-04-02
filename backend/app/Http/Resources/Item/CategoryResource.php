<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        // Используем кешированные данные категории
        $data = $this->getAllData();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $data['icon_url'],
            'main_image' => $data['main_image_url'],
            'background_image' => $data['background_image_url'],
            'subcategories_count' => $data['subcategories_count'],
            'products_count' => $data['products_count'],
            'subcategories' => SubcategoryResource::collection($this->subcategories),
        ];
    }
}

class SubcategoryResource extends JsonResource
{
    public function toArray($request)
    {
        $data = $this->getAllData();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $data['icon_url'],
            'category_id' => $this->category_id,
            'category_name' => $data['category_name'],
            'products_count' => $data['products_count'],
            'sub_subcategories_count' => $data['sub_subcategories_count'],
            'sub_subcategories' => SubSubcategoryResource::collection($this->sub_subcategories),
        ];
    }
}

class SubSubcategoryResource extends JsonResource
{
    public function toArray($request)
    {
        $data = $this->getAllData();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $data['icon_url'],
            'subcategory_id' => $this->subcategory_id,
            'subcategory_name' => $data['subcategory_name'],
            'category_name' => $data['category_name'],
            'products_count' => $data['products_count'],
        ];
    }
}