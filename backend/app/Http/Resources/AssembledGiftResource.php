<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssembledGiftResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'product_id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => (float) $this->price,
            'assembly_instructions' => $this->assembly_instructions,
            'inventories' => $this->inventories->map(fn ($inventory): array => [
                'warehouse_id' => $inventory->warehouse_id,
                'warehouse_name' => $inventory->warehouse?->name,
                'quantity' => (int) $inventory->quantity,
                'reserved_online_quantity' => (int) $inventory->reserved_online_quantity,
                'reserved_seller_quantity' => (int) $inventory->reserved_seller_quantity,
                'available_quantity' => $inventory->availableQuantity(),
            ])->values(),
        ];
    }
}
