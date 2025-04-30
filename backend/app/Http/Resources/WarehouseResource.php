<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'numbwarehouse' => $this->numbwarehouse,
            'volume' => $this->volume,
        ];
    }
}
