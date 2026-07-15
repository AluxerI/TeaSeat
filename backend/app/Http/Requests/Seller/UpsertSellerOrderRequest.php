<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSellerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::payloadRules();
    }

    public static function payloadRules(): array
    {
        return [
            'client_order_id' => ['required', 'uuid'],
            'revision' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'integer'],
            'occurred_at' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,card'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.pricing_token' => ['required', 'string', 'max:30000'],
        ];
    }
}
