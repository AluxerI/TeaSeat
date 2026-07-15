<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GiftSizeProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind,
            'width_cells' => (int) $this->width_cells,
            'height_cells' => (int) $this->height_cells,
            'can_rotate' => (bool) $this->can_rotate,
            'max_weight_grams' => $this->max_weight_grams,
            'default_markup_amount' => (float) $this->default_markup_amount,
            'simple_constructor_enabled' => (bool) $this->simple_constructor_enabled,
            'simple_requirements' => $this->simpleRequirements(),
        ];
    }
}
