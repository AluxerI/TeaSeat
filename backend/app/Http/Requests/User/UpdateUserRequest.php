<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('edit users');
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'timezone' => 'sometimes|timezone',
        ];
    }
}
