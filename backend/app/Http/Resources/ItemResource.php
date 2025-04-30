<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


class ItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->nameProduct->nameitem ?? null,
            'price' => $this->priceproduct,
            'description' => $this->nameProduct->description ?? null, // Связанные данные
            'stock' => $this->itemsOnWarehouse ? $this->itemsOnWarehouse->sum('volume') : 0,
            'warehouses' => WarehouseResource::collection($this->itemsOnWarehouse ?? []),
        ];
    }
}
