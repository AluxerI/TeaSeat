<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            
            'provider' => $this->provider, // 'google', 'vkontakte', etc
            'provider_id' => $this->provider_id,
            'phone' => $this->phone,
            'phone_verified_at' => $this->phone_verified_at,
            'is_active' => $this->is_active,

            'address_client'=> $this-> getAddressPath(),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getAddressPath()
    {
        // Проверяем, загружено ли отношение addresses
        if (!$this->addresses || $this->addresses->isEmpty()) {
            return null; // или return []; если предпочтительнее вернуть пустой массив
        }

        // Преобразуем коллекцию адресов в массив
        return $this->addresses->map(function ($address) {
            return [
                'id' => $address->id,
                'user_id' => $address->id_user,
                'street' => $address->street, 
                'city' => $address->city,
                'postal_code' => $address->postal_code,
                'created_at' => $address->created_at,
                'updated_at' => $address->updated_at,
            ];
        });
    }
    
}
