<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        // Используем кешированные данные из модели User
        $userData = $this->getAllData();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->format('d.m.Y H:i'),
            
            'provider' => $this->provider,
            'provider_id' => $this->provider_id,
            'phone' => $this->phone,
            'phone_verified_at' => $this->phone_verified_at?->format('d.m.Y H:i'),
            'is_active' => $this->is_active,

            'addresses' => AddressClientResource::collection($this->whenLoaded('addresses')),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            
            // Статистика из кеша
            'stats' => [
                'orders_count' => $userData['orders_count'] ?? $this->orders()->count(),
                'reviews_count' => $userData['reviews_count'] ?? $this->reviews()->count(),
            ],
            
            'created_at' => $userData['created_at'] ?? $this->created_at?->format('d.m.Y'),
            'updated_at' => $this->updated_at?->format('d.m.Y H:i'),
        ];
    }
}