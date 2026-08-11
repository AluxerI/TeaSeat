<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Services\DeliveryScheduleService;
use Illuminate\Http\Resources\Json\JsonResource;

class CourierDeliveryResource extends JsonResource
{
    public function toArray($request): array
    {
        $isTransfer = $this->isWarehouseTransfer();
        $customerOrder = $this->parentOrder ?: $this->resource;
        $customer = $customerOrder->user;
        $address = $customerOrder->shippingAddress;
        $user = $request->user();
        $isOwner = $user && (int) $this->courier_id === (int) $user->id;
        $canOperate = $isOwner || $user?->hasRole(\App\Models\User::ROLE_ADMIN);
        $amountToCollect = !$isTransfer
            && $customerOrder->payment_method === Order::PAYMENT_CASH
                ? (float) $customerOrder->final_total
                : 0.0;
        $scheduleService = app(DeliveryScheduleService::class);
        $claimWindowOpen = $scheduleService
            ->courierClaimWindowIsOpen($this->resource);
        $claimOpensAt = $scheduleService
            ->courierClaimOpensAt($customerOrder);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'delivery_kind' => $isTransfer ? 'transfer' : 'customer',
            'status' => $this->status,
            'status_name' => $this->status_name,
            'courier' => $this->courier ? [
                'id' => $this->courier->id,
                'name' => $this->courier->name,
                'phone' => $this->courier->phone,
            ] : null,
            'pickup' => $this->warehouse ? [
                'warehouse_id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'city' => $this->warehouse->city,
                'address' => $this->warehouse->location,
            ] : null,
            'transfer_destination' => $isTransfer && $this->destinationWarehouse ? [
                'warehouse_id' => $this->destinationWarehouse->id,
                'name' => $this->destinationWarehouse->name,
                'city' => $this->destinationWarehouse->city,
                'address' => $this->destinationWarehouse->location,
            ] : null,
            'customer' => $isTransfer ? null : [
                'id' => $customer?->id,
                'name' => $customerOrder->contact_name ?: $customer?->name,
                'email' => $customerOrder->contact_email ?: $customer?->email,
                'phone' => $customerOrder->contact_phone ?: $customer?->phone,
            ],
            'delivery_address' => !$isTransfer && $address ? [
                'id' => $address->id,
                'city' => $address->city,
                'street' => $address->street,
                'postal_code' => $address->postal_code,
                'full_address' => $address->getFullAddress(),
            ] : null,
            'delivery_method' => !$isTransfer && $customerOrder->deliveryMethod ? [
                'id' => $customerOrder->deliveryMethod->id,
                'name' => $customerOrder->deliveryMethod->name,
                'type' => $customerOrder->deliveryMethod->type,
                'provider_code' => $customerOrder->deliveryMethod->provider_code,
            ] : null,
            'scheduled_window' => !$isTransfer
                && $customerOrder->scheduled_delivery_date !== null ? [
                    'date' => $customerOrder->scheduled_delivery_date->toDateString(),
                    'time_from' => substr((string) $customerOrder->delivery_time_from, 0, 5),
                    'time_to' => substr((string) $customerOrder->delivery_time_to, 0, 5),
                    'slot_id' => (int) $customerOrder->delivery_time_slot_id,
                    'claim_opens_at' => $claimOpensAt?->toIso8601String(),
                ] : null,
            'payment' => $isTransfer ? null : [
                'method' => $customerOrder->payment_method,
                'order_total' => (float) $customerOrder->final_total,
                'amount_to_collect' => $amountToCollect,
            ],
            'items' => $this->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => (int) $item->quantity,
                'stock_unit' => $item->stock_unit,
            ])->values(),
            'timestamps' => [
                'ready_at' => $this->ready_for_delivery_at?->toIso8601String(),
                'assigned_at' => $this->courier_assigned_at?->toIso8601String(),
                'started_at' => $this->shipped_at?->toIso8601String(),
                'arrived_at' => $this->courier_arrived_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
            ],
            'actions' => [
                'can_claim' => $this->status === Order::STATUS_READY_FOR_DELIVERY
                    && $this->courier_id === null
                    && $claimWindowOpen,
                'can_release' => $this->status === Order::STATUS_READY_FOR_DELIVERY
                    && $canOperate,
                'can_start' => $this->status === Order::STATUS_READY_FOR_DELIVERY
                    && $canOperate,
                'can_deliver' => $this->status === Order::STATUS_SHIPPED
                    && $canOperate,
            ],
        ];
    }
}
