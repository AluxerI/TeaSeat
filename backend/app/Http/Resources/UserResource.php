<?php

namespace App\Http\Resources;

use App\Models\User;
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
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'work_locations' => $this->whenLoaded('activeWarehouses', function () {
                return $this->activeWarehouses->map(fn ($warehouse) => [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'city' => $warehouse->city,
                    'type' => $warehouse->type,
                    'is_online_fulfillment_enabled' => $warehouse->is_online_fulfillment_enabled,
                    'is_delivery_hub' => $warehouse->is_delivery_hub,
                ])->values();
            }),
            'capabilities' => [
                'admin' => $this->hasRole(User::ROLE_ADMIN),
                'manager' => $this->hasRole(User::ROLE_MANAGER),
                'seller' => $this->hasRole(User::ROLE_SELLER),
                'picker' => $this->hasRole(User::ROLE_PICKER),
                'courier' => $this->hasRole(User::ROLE_COURIER),
                'can_access_filament' => $this->is_active
                    && $this->hasAnyRole([User::ROLE_ADMIN, User::ROLE_MANAGER]),
            ],
            
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
