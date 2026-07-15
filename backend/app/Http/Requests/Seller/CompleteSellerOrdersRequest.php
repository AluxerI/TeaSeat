<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class CompleteSellerOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1', 'max:100'],
            'orders.*.order_id' => ['required', 'integer', 'distinct'],
            'orders.*.revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
