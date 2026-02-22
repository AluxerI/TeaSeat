<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon_url' => $this->icon_url,
            'images' => $this->images_data,
            'subcategories' => SubcategoryResource::collection($this->subcategories),
        ];
    }
}

class SubcategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon_url' => $this->icon_url,
            'category_id' => $this->category_id,
            'sub_subcategories' => Sub_SubcategoryResource::collection($this->sub_subcategories),
        ];
    }
}

class Sub_SubcategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon_url' => $this->icon_url,
            'subcategory_id' => $this->subcategory_id,
        ];
    }
}