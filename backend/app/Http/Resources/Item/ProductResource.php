<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\PriceCalculatorService;


class ProductResource extends JsonResource
{

    public function toArray($request)
    {
        $priceData = app(PriceCalculatorService::class)
            ->calculateForProduct($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'ingredients' => $this->ingredients,
            'description' => $this->description,
            
            'image' => $this->main_image_url, // для обратной совместимости
            'images' => [
                'main' => $this->main_image_url,
                'background' => $this->background_image_url,
                // можно добавить все изображения, если нужно в каталоге
                'all' => $this->images->map(fn($img) => [
                    'url' => $img->url,
                    'is_main' => $img->is_main,
                    'is_background' => $img->is_background,
                ]),
            ],

            'price' => $this->price,
            'pricing' => $priceData,
            'weight_grams' => $this->weight_grams,
            'brand' => $this->brand->name ?? null,
            'category_path' => $this->getCategoryPath(),
            'inventory' => $this->getInventoryData(),
            'total_quantity' => $this->inventories->sum('quantity'),
            'is_available' => $this->inventories->sum('quantity') > 0,
            'sold_count' => $this->sold_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at, 
        ];
    }

    protected function getCategoryPath()
    {
        $mainSubSubcategory = $this->sub_subcategories->first();
        if (!$mainSubSubcategory) return null;

        return [
            'category' => $mainSubSubcategory->subcategory->category->name ?? null,
            'subcategory' => $mainSubSubcategory->subcategory->name ?? null,
            'sub_subcategory' => $mainSubSubcategory->name,
        ];
    }

    protected function getInventoryData()
    {
        return $this->inventories->map(function ($inventory) {
            return [
                'warehouse_id' => $inventory->warehouse_id,
                'warehouse_name' => $inventory->warehouse->name,
                'quantity' => $inventory->quantity,
                'last_restock_date' => $inventory->last_restock_date,
            ];
        });
    }
}