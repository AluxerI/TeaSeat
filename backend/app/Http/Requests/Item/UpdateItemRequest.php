<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit products');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'price' => 'sometimes|required|numeric|min:0',
            'weight_grams' => 'sometimes|required|integer|min:0',
            'ingredients' => 'nullable|string',
            'brand_id' => 'nullable|exists:brands,id',
            'sub_subcategory_ids' => 'nullable|array',
            'sub_subcategory_ids.*' => 'exists:sub_subcategories,id'
        ];
    }
}
