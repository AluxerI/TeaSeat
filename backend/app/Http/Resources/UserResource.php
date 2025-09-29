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

            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
