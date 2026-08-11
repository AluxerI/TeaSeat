<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryMethodResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cost' => $this->cost,
            'type' => $this->type,
            'provider_code' => $this->provider_code,
            'estimated_days' => $this->getEstimatedDaysFormatted(),
            'requires_scheduling' => $this->requiresScheduling(),
            'booking_horizon_days' => $this->requiresScheduling()
                ? max(1, (int) config('delivery.booking_horizon_days', 30))
                : null,
            'details' => [
                'min_days' => $this->estimated_days_min,
                'max_days' => $this->estimated_days_max,
            ],
        ];
    }
}
