<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create products');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'weight_grams' => 'required|integer|min:0',
            'ingredients' => 'nullable|string',
            'brand_id' => 'nullable|exists:brands,id',
            'sub_subcategory_ids' => 'nullable|array',
            'sub_subcategory_ids.*' => 'exists:sub_subcategories,id'
        ];
    }
}
