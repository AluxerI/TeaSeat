<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class SellerSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.event_id' => ['required', 'uuid', 'distinct'],
            'events.*.action' => ['required', 'in:upsert,cancel,complete,escalate'],
            'events.*.revision' => ['required', 'integer', 'min:1'],
            // Эти поля необязательны для команд cancel/complete/escalate, но
            // должны остаться в validated() у события upsert. Полную проверку
            // обязательности и distinct внутри одного заказа выполняет
            // UpsertSellerOrderRequest в SyncController.
            'events.*.order_id' => ['nullable', 'integer'],
            'events.*.client_order_id' => ['nullable', 'uuid'],
            'events.*.warehouse_id' => ['nullable', 'integer'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.payment_method' => ['nullable', 'in:cash,card'],
            'events.*.items' => ['nullable', 'array', 'max:100'],
            'events.*.items.*' => ['array'],
            'events.*.items.*.product_id' => ['nullable', 'integer'],
            'events.*.items.*.quantity' => ['nullable', 'integer'],
            'events.*.items.*.pricing_token' => ['nullable', 'string', 'max:30000'],
        ];
    }
}
