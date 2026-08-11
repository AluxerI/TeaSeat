<?php

namespace App\Http\Resources;

use App\Models\FulfillmentIssue;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ManagerOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $issues = $this->allFulfillmentIssues();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'sales_channel' => $this->sales_channel,
            'order_type' => $this->is_supplier_order ? 'supplier' : 'regular',
            'was_edited' => (bool) $this->was_edited,
            'customer' => $this->customerData(),
            'totals' => [
                'products_total' => (float) $this->products_total,
                'promotion_discount' => (float) ($this->promotion_discount ?? 0),
                'personal_discount' => (float) ($this->personal_discount ?? 0),
                'cart_discount' => (float) ($this->cart_discount ?? 0),
                'shipping_cost' => (float) $this->shipping_cost,
                'shipping_discount' => (float) ($this->shipping_discount ?? 0),
                'final_total' => (float) $this->final_total,
            ],
            'payment' => [
                'method' => $this->payment_method,
                'paid_at' => $this->paid_at?->toIso8601String(),
                'amount_to_collect' => $this->payment_method === Order::PAYMENT_CASH
                    && $this->paid_at === null
                    ? (float) $this->final_total
                    : 0.0,
            ],
            'pricing' => $this->when(
                $this->relationLoaded('items'),
                fn () => [
                    'applied_promotion_code' => $this->applied_promotion_code,
                    'selected_discount' => $this->pricing_snapshot['selected_discount']
                        ?? null,
                ]
            ),
            'delivery' => [
                'method' => $this->deliveryMethodData(),
                'scheduled_window' => $this->scheduledWindowData(),
                'address' => $this->shippingAddressData(),
                'tracking_number' => $this->tracking_number,
                'warehouse' => $this->warehouseData($this->warehouse),
                'destination_warehouse' => $this->warehouseData(
                    $this->destinationWarehouse
                ),
            ],
            'staff' => [
                'seller' => $this->sales_channel === Order::SALES_CHANNEL_SELLER
                    ? $this->userData($this->user)
                    : null,
                'picker' => $this->userData($this->picker),
                'courier' => $this->userData($this->courier),
                'seller_device' => $this->sellerDeviceData(),
            ],
            'fulfillment_summary' => [
                'parts_count' => $this->relationLoaded('partialOrders')
                    ? $this->partialOrders->count()
                    : null,
                'issues_count' => $issues->count(),
                'open_issues_count' => $issues
                    ->where('status', '!=', FulfillmentIssue::STATUS_CLOSED)
                    ->count(),
            ],
            'actions' => $this->orderActionsData($request, $issues),
            'manager_access' => $this->managerAccessData($request),
            'supplier_order' => $this->when(
                $this->relationLoaded('supplierOrder'),
                fn () => $this->supplierOrder ? [
                    'id' => $this->supplierOrder->id,
                    'supplier' => $this->supplierOrder->supplier ? [
                        'id' => $this->supplierOrder->supplier->id,
                        'name' => $this->supplierOrder->supplier->name,
                    ] : null,
                    'status' => $this->supplierOrder->status,
                    'scheduled_date' => $this->supplierOrder->scheduled_date?->toDateString(),
                    'delivery_date' => $this->supplierOrder->delivery_date?->toDateString(),
                ] : null
            ),
            'items' => $this->when(
                $this->relationLoaded('items'),
                fn () => OrderItemResource::collection($this->items)
            ),
            'gifts' => $this->when(
                $this->relationLoaded('gifts'),
                fn () => OrderGiftResource::collection($this->gifts)
            ),
            'partial_orders' => $this->when(
                $this->relationLoaded('partialOrders'),
                fn () => $this->partialOrders
                    ->map(fn (Order $part): array => $this->partialOrderData($part))
                    ->values()
                    ->all()
            ),
            'fulfillment_issues' => $this->when(
                $this->hasDetailedIssueRelations(),
                fn () => $issues
                    ->map(fn (FulfillmentIssue $issue): array =>
                        $this->fulfillmentIssueData($issue, $request))
                    ->values()
                    ->all()
            ),
            'inventory_movements' => $this->when(
                $this->relationLoaded('inventoryMovements'),
                fn () => $this->allInventoryMovements()
                    ->map(fn ($movement): array => [
                        'id' => $movement->id,
                        'order_id' => $movement->order_id,
                        'product' => $movement->product ? [
                            'id' => $movement->product->id,
                            'name' => $movement->product->name,
                        ] : null,
                        'warehouse' => $this->warehouseData($movement->warehouse),
                        'actor' => $this->userData($movement->actor),
                        'type' => $movement->type,
                        'physical_delta' => (int) $movement->physical_delta,
                        'reserved_online_delta' => (int) $movement->reserved_online_delta,
                        'reserved_seller_delta' => (int) $movement->reserved_seller_delta,
                        'balances' => [
                            'physical_before' => (int) $movement->physical_before,
                            'physical_after' => (int) $movement->physical_after,
                            'reserved_online_before' => (int) $movement->reserved_online_before,
                            'reserved_online_after' => (int) $movement->reserved_online_after,
                            'reserved_seller_before' => (int) $movement->reserved_seller_before,
                            'reserved_seller_after' => (int) $movement->reserved_seller_after,
                        ],
                        'reason' => $movement->reason,
                        'created_at' => $movement->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all()
            ),
            'status_history' => $this->when(
                $this->relationLoaded('statusHistory'),
                fn () => $this->statusHistory
                    ->map(fn ($history): array => [
                        'order_id' => $history->order_id,
                        'from_status' => $history->from_status,
                        'from_status_name' => Order::getStatusName($history->from_status),
                        'to_status' => $history->to_status,
                        'to_status_name' => Order::getStatusName($history->to_status),
                        'changed_by' => $this->userData($history->changedBy),
                        'notes' => $history->notes,
                        'created_at' => $history->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all()
            ),
            'manager_adjustments' => $this->when(
                $this->relationLoaded('managerAdjustments'),
                fn () => ManagerOrderAdjustmentResource::collection(
                    $this->managerAdjustments
                )
            ),
            'notes' => $this->when(
                $this->relationLoaded('items'),
                fn () => [
                    'customer' => $this->customer_notes,
                    'internal' => $this->internal_notes,
                ]
            ),
            'stock' => [
                'reserved_at' => $this->stock_reserved_at?->toIso8601String(),
                'committed_at' => $this->stock_committed_at?->toIso8601String(),
                'released_at' => $this->stock_released_at?->toIso8601String(),
            ],
            'timestamps' => [
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
                'confirmed_at' => $this->confirmed_at?->toIso8601String(),
                'picking_started_at' => $this->picking_started_at?->toIso8601String(),
                'ready_for_delivery_at' => $this->ready_for_delivery_at?->toIso8601String(),
                'courier_assigned_at' => $this->courier_assigned_at?->toIso8601String(),
                'courier_arrived_at' => $this->courier_arrived_at?->toIso8601String(),
                'shipped_at' => $this->shipped_at?->toIso8601String(),
                'received_at' => $this->received_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
                'seller_occurred_at' => $this->seller_occurred_at?->toIso8601String(),
                'seller_synced_at' => $this->seller_synced_at?->toIso8601String(),
                'seller_escalated_at' => $this->seller_escalated_at?->toIso8601String(),
            ],
        ];
    }

    private function customerData(): ?array
    {
        if (!$this->relationLoaded('user') && !$this->contact_name) {
            return null;
        }

        return [
            'id' => $this->user?->id,
            'name' => $this->contact_name ?: $this->user?->name,
            'email' => $this->contact_email ?: $this->user?->email,
            'phone' => $this->contact_phone ?: $this->user?->phone,
        ];
    }

    private function deliveryMethodData(): ?array
    {
        if (!$this->relationLoaded('deliveryMethod') || !$this->deliveryMethod) {
            return null;
        }

        return [
            'id' => $this->deliveryMethod->id,
            'name' => $this->deliveryMethod->name,
            'type' => $this->deliveryMethod->type,
            'provider_code' => $this->deliveryMethod->provider_code,
            'estimated_days_min' => $this->deliveryMethod->estimated_days_min,
            'estimated_days_max' => $this->deliveryMethod->estimated_days_max,
        ];
    }

    private function shippingAddressData(): ?array
    {
        if (!$this->relationLoaded('shippingAddress') || !$this->shippingAddress) {
            return null;
        }

        return [
            'id' => $this->shippingAddress->id,
            'street' => $this->shippingAddress->street,
            'city' => $this->shippingAddress->city,
            'postal_code' => $this->shippingAddress->postal_code,
            'full_address' => $this->shippingAddress->getFullAddress(),
        ];
    }

    private function scheduledWindowData(): ?array
    {
        if ($this->scheduled_delivery_date === null) {
            return null;
        }

        return [
            'date' => $this->scheduled_delivery_date->toDateString(),
            'time_from' => substr((string) $this->delivery_time_from, 0, 5),
            'time_to' => substr((string) $this->delivery_time_to, 0, 5),
            'slot_id' => $this->delivery_time_slot_id
                ? (int) $this->delivery_time_slot_id
                : null,
        ];
    }

    private function warehouseData($warehouse): ?array
    {
        if (!$warehouse) {
            return null;
        }

        return [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'city' => $warehouse->city,
            'type' => $warehouse->type,
            'is_active' => (bool) $warehouse->is_active,
        ];
    }

    private function userData($user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];
    }

    private function sellerDeviceData(): ?array
    {
        if (!$this->relationLoaded('sellerDevice') || !$this->sellerDevice) {
            return null;
        }

        return [
            'id' => $this->sellerDevice->id,
            'device_uuid' => $this->sellerDevice->device_uuid,
            'name' => $this->sellerDevice->name,
            'last_sync_at' => $this->sellerDevice->last_sync_at?->toIso8601String(),
        ];
    }

    private function partialOrderData(Order $part): array
    {
        return [
            'id' => $part->id,
            'order_number' => $part->order_number,
            'status' => $part->status,
            'status_name' => $part->status_name,
            'warehouse' => $this->warehouseData($part->warehouse),
            'destination_warehouse' => $this->warehouseData(
                $part->destinationWarehouse
            ),
            'picker' => $this->userData($part->picker),
            'courier' => $this->userData($part->courier),
            'final_total' => (float) $part->final_total,
            'stock' => [
                'reserved_at' => $part->stock_reserved_at?->toIso8601String(),
                'committed_at' => $part->stock_committed_at?->toIso8601String(),
                'released_at' => $part->stock_released_at?->toIso8601String(),
            ],
            'items' => $part->relationLoaded('items')
                ? OrderItemResource::collection($part->items)
                : null,
            'gifts' => $part->relationLoaded('gifts')
                ? OrderGiftResource::collection($part->gifts)
                : null,
            'internal_notes' => $part->relationLoaded('items')
                ? $part->internal_notes
                : null,
            'status_history' => $part->relationLoaded('statusHistory')
                ? $part->statusHistory
                    ->map(fn ($history): array => [
                        'from_status' => $history->from_status,
                        'to_status' => $history->to_status,
                        'changed_by' => $this->userData($history->changedBy),
                        'notes' => $history->notes,
                        'created_at' => $history->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all()
                : null,
        ];
    }

    private function managerAccessData($request): array
    {
        $manager = $request->user();
        $isAdmin = $manager?->hasRole(User::ROLE_ADMIN) ?? false;
        $warehouseIds = collect(
            $request->attributes->get('manager_active_warehouse_ids', [])
        )->map(fn ($id): int => (int) $id);
        $orders = $this->orderGraph();
        $requiredWarehouseIds = $this->requiredWarehouseIds($orders);
        $allLocationsAssigned = $isAdmin || (
            $requiredWarehouseIds->isNotEmpty()
            && $requiredWarehouseIds->diff($warehouseIds)->isEmpty()
        );

        $assignedOrderIds = $orders
            ->filter(function (Order $order) use ($isAdmin, $warehouseIds): bool {
                return $isAdmin
                    || $warehouseIds->contains((int) $order->warehouse_id)
                    || $warehouseIds->contains((int) $order->destination_warehouse_id);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return [
            'full_order_visible' => true,
            'read_only_contract' => false,
            'assigned_fulfillment_order_ids' => $assignedOrderIds,
            'required_warehouse_ids' => $requiredWarehouseIds->all(),
            'all_locations_assigned' => $allLocationsAssigned,
        ];
    }

    private function orderActionsData($request, Collection $issues): array
    {
        $manager = $request->user();
        $isAdmin = $manager?->hasRole(User::ROLE_ADMIN) ?? false;
        $warehouseIds = collect(
            $request->attributes->get('manager_active_warehouse_ids', [])
        )->map(fn ($id): int => (int) $id);
        $orders = $this->orderGraph();
        $requiredWarehouseIds = $this->requiredWarehouseIds($orders);
        $allLocationsAssigned = $isAdmin || (
            $requiredWarehouseIds->isNotEmpty()
            && $requiredWarehouseIds->diff($warehouseIds)->isEmpty()
        );
        $isOnlineOrder = $this->sales_channel === Order::SALES_CHANNEL_ONLINE
            && !$this->is_supplier_order;
        $hasOpenIssues = $issues
            ->contains(fn (FulfillmentIssue $issue): bool =>
                $issue->status !== FulfillmentIssue::STATUS_CLOSED);
        $hasStartedFulfillment = $this->stock_committed_at !== null
            || $this->picking_started_at !== null
            || $this->ready_for_delivery_at !== null
            || $this->picker_id !== null
            || $this->courier_id !== null
            || (
                $this->relationLoaded('partialOrders')
                && $this->partialOrders->contains(function (Order $part): bool {
                    return $part->stock_committed_at !== null
                        || $part->picking_started_at !== null
                        || $part->ready_for_delivery_at !== null
                        || $part->picker_id !== null
                        || $part->courier_id !== null
                        || in_array($part->status, [
                            Order::STATUS_PROCESSING,
                            Order::STATUS_READY_FOR_DELIVERY,
                            Order::STATUS_SHIPPED,
                            Order::STATUS_AWAITING_RECEIPT,
                            Order::STATUS_DELIVERED,
                        ], true);
                })
            );
        $cancellableStatus = in_array($this->status, [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SELLER_REVIEW,
            Order::STATUS_MANAGER_REVIEW,
        ], true);

        return [
            'can_add_internal_note' => true,
            'can_confirm' => $allLocationsAssigned
                && $isOnlineOrder
                && $this->status === Order::STATUS_PENDING
                && !$hasOpenIssues,
            'can_cancel' => $allLocationsAssigned
                && $isOnlineOrder
                && $this->paid_at === null
                && $cancellableStatus
                && !$hasStartedFulfillment,
            'can_reschedule' => $allLocationsAssigned
                && $isOnlineOrder
                && $this->deliveryMethod?->requiresScheduling()
                && $this->courier_id === null
                && !in_array($this->status, [
                    Order::STATUS_SHIPPED,
                    Order::STATUS_AWAITING_RECEIPT,
                    Order::STATUS_DELIVERED,
                    Order::STATUS_CANCELLED,
                    Order::STATUS_COMPLETED,
                ], true),
            'can_modify_items' => $allLocationsAssigned
                && $isOnlineOrder
                && $this->paid_at === null
                && !$hasStartedFulfillment
                && in_array($this->status, [
                    Order::STATUS_PENDING,
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_MANAGER_REVIEW,
                ], true),
        ];
    }

    private function orderGraph(): Collection
    {
        $orders = collect([$this->resource]);

        if ($this->relationLoaded('partialOrders')) {
            $orders = $orders->concat($this->partialOrders);
        }

        return $orders;
    }

    private function requiredWarehouseIds(Collection $orders): Collection
    {
        return $orders
            ->flatMap(fn (Order $order): array => [
                $order->warehouse_id,
                $order->destination_warehouse_id,
            ])
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function allFulfillmentIssues(): Collection
    {
        $issues = $this->relationLoaded('fulfillmentIssues')
            ? $this->fulfillmentIssues
            : collect();

        if ($this->relationLoaded('partialOrders')) {
            foreach ($this->partialOrders as $part) {
                if ($part->relationLoaded('fulfillmentIssues')) {
                    $issues = $issues->concat($part->fulfillmentIssues);
                }
            }
        }

        return $issues->unique('id')->values();
    }

    private function allInventoryMovements(): Collection
    {
        $movements = $this->inventoryMovements;

        if ($this->relationLoaded('partialOrders')) {
            foreach ($this->partialOrders as $part) {
                if ($part->relationLoaded('inventoryMovements')) {
                    $movements = $movements->concat($part->inventoryMovements);
                }
            }
        }

        return $movements->unique('id')->sortBy('id')->values();
    }

    private function hasDetailedIssueRelations(): bool
    {
        if (!$this->relationLoaded('fulfillmentIssues')) {
            return false;
        }

        $first = $this->allFulfillmentIssues()->first();

        return $first === null || $first->relationLoaded('product');
    }

    private function fulfillmentIssueData(
        FulfillmentIssue $issue,
        $request
    ): array {
        $manager = $request->user();
        $isAdmin = $manager?->hasRole(User::ROLE_ADMIN) ?? false;
        $warehouseIds = collect(
            $request->attributes->get('manager_active_warehouse_ids', [])
        )->map(fn ($id): int => (int) $id);
        $accessible = $isAdmin
            || $warehouseIds->contains((int) $issue->warehouse_id);
        $ownerOrAdmin = $isAdmin
            || (int) $issue->manager_id === (int) ($manager?->id);

        return [
            'id' => $issue->id,
            'source_order_id' => $issue->source_order_id,
            'product' => $issue->product ? [
                'id' => $issue->product->id,
                'name' => $issue->product->name,
                'stock_unit' => $issue->product->stockUnit(),
            ] : null,
            'warehouse' => $this->warehouseData($issue->warehouse),
            'manager' => $this->userData($issue->manager),
            'reason' => $issue->reason,
            'reason_message' => match ($issue->reason) {
                FulfillmentIssue::REASON_ONLINE_RESERVATION_CONFLICT =>
                    'После физической продажи не хватает товара для интернет-резервов.',
                FulfillmentIssue::REASON_PHYSICAL_STOCK_DISCREPANCY =>
                    'Физический остаток отличается от учётного.',
                default => 'Требуется проверка менеджера.',
            },
            'shortage_quantity' => (int) $issue->shortage_quantity,
            'reserved_online_before' => (int) $issue->reserved_online_before,
            'reserved_seller_before' => (int) $issue->reserved_seller_before,
            'status' => $issue->status,
            'manager_accessible' => $accessible,
            'actions' => [
                'can_take' => $accessible
                    && $issue->status === FulfillmentIssue::STATUS_WAITING,
                'can_release' => $accessible
                    && $ownerOrAdmin
                    && $issue->status === FulfillmentIssue::STATUS_IN_REVIEW,
                'can_close' => $accessible
                    && $ownerOrAdmin
                    && $issue->status === FulfillmentIssue::STATUS_IN_REVIEW,
            ],
            'created_at' => $issue->created_at?->toIso8601String(),
            'updated_at' => $issue->updated_at?->toIso8601String(),
        ];
    }
}
