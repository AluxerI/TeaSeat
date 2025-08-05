<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;


class ProductsResource extends JsonResource
{
    public function toArray($request)
    {
        $mainSubSub = $this->sub_subcategories->first();
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'weight_grams' => $this->weight_grams,
            
            'sub_subcategory' => $mainSubSub,
            
            'brand' => $this->brand->name ?? null,
            'inventory' => $this->getInventoryData(),
            'total_quantity' => $this->inventories->sum('quantity'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getSubSubcategoryData()
    {
        return $this->Sub_subcategory ? [
            'id' => $this->Sub_subcategory->id,
            'name' => $this->Sub_subcategory->name
        ] : null;
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