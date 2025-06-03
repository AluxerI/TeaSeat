<?php

namespace App\Http\Resources\Item;

use Illuminate\Http\Resources\Json\JsonResource;


class ProductsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->nameProduct->name ?? null,
            'description' => $this->nameProduct->description ?? null,
            'price' => $this->priceproduct,
            // 'stock' => $this->itemsOnWarehouse->sum('quantity') ?? 0,
            'warehouses' => WarehouseResource::collection($this->itemsOnWarehouse ?? [])
        ];
    }
}
