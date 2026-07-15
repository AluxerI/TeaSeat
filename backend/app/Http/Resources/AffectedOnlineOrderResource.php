<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AffectedOnlineOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $fulfillmentOrder = $this->order;
        $customerOrder = $fulfillmentOrder->parentOrder ?: $fulfillmentOrder;
        $customer = $customerOrder->user;
        $address = $customerOrder->shippingAddress;
        $deliveryMethod = $customerOrder->deliveryMethod;

        return [
            'order_product_id' => $this->id,
            'fulfillment_order_id' => $fulfillmentOrder->id,
            'customer_order_id' => $customerOrder->id,
            'customer_order_number' => $customerOrder->order_number,
            'reserved_quantity' => (int) $this->quantity,
            'stock_unit' => $this->stock_unit,
            'line_total' => (float) $this->total_price,
            'fulfillment_status' => $fulfillmentOrder->status,
            'customer_order_status' => $customerOrder->status,
            'customer' => [
                'id' => $customer?->id,
                'name' => $customerOrder->contact_name ?: $customer?->name,
                'email' => $customerOrder->contact_email ?: $customer?->email,
                'phone' => $customerOrder->contact_phone ?: $customer?->phone,
            ],
            'delivery' => [
                'method' => $deliveryMethod ? [
                    'id' => $deliveryMethod->id,
                    'name' => $deliveryMethod->name,
                ] : null,
                'address' => $address ? [
                    'id' => $address->id,
                    'city' => $address->city,
                    'street' => $address->street,
                    'postal_code' => $address->postal_code,
                    'full_address' => $address->getFullAddress(),
                ] : null,
            ],
            'timestamps' => [
                'created_at' => $customerOrder->created_at?->toIso8601String(),
                'reserved_at' => $fulfillmentOrder->stock_reserved_at?->toIso8601String(),
            ],
        ];
    }
}
