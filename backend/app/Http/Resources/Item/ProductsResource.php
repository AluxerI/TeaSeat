<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;


class ProductsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'weight_grams' => $this->weight_grams,
            // 'category' => $this->subcategory->category->name,
            // 'subcategory' => $this->subcategory->name,
            'brand' => $this->brand->name ?? null,   
            'inventory' => $this->getInventoryData(),
            'total_quantity' => $this->inventories->sum('quantity'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // 'warehouses' => WarehouseResource::collection($this->itemsOnWarehouse ?? [])
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
