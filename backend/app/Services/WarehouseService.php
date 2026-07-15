<?php

namespace App\Services;

use App\Models\AddressClient;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarehouseService
{
    public function determineWarehousesForOrder(Order $order, AddressClient $shippingAddress): array
    {
        $orderItems = $order->items()->with('product')->get();

        if ($orderItems->isEmpty()) {
            throw new DomainException('Заказ не содержит товаров для распределения');
        }

        foreach ($orderItems as $item) {
            $saleStep = max(1, (int) $item->sale_step);
            if ((int) $item->quantity <= 0 || (int) $item->quantity % $saleStep !== 0) {
                throw new DomainException('В заказе указано недопустимое количество товара');
            }
        }

        $warehouses = Warehouse::query()
            ->where('city', $shippingAddress->city)
            ->onlineFulfillment()
            // Для интернет-заказа сначала используем склад, магазин — как резервный источник.
            ->orderByRaw(
                'CASE WHEN type = ? THEN 0 ELSE 1 END',
                [Warehouse::TYPE_WAREHOUSE]
            )
            ->orderBy('id')
            ->get();

        if ($warehouses->isEmpty()) {
            throw new DomainException(
                "В городе {$shippingAddress->city} нет активных точек выдачи интернет-заказов"
            );
        }

        $allocation = $this->allocateItemsToWarehouses($orderItems, $warehouses);
        $this->validateAllocationCompleteness(
            $allocation,
            $orderItems,
            $shippingAddress->city
        );

        return $allocation;
    }

    private function allocateItemsToWarehouses(
        Collection $orderItems,
        Collection $warehouses
    ): array {
        $allocation = [];
        $remainingItems = $orderItems->pluck('quantity', 'id')->toArray();

        foreach ($warehouses as $warehouse) {
            $warehouseAllocation = [
                'warehouse_id' => $warehouse->id,
                'items' => [],
            ];

            $inventoryByProduct = $warehouse->inventories()
                ->whereIn('product_id', $orderItems->pluck('product_id')->unique())
                ->get()
                ->keyBy('product_id');
            $availableByProduct = $inventoryByProduct->map(
                fn (Inventory $inventory): int => $inventory->availableQuantity()
            )->all();

            foreach ($orderItems as $item) {
                $remaining = (int) ($remainingItems[$item->id] ?? 0);
                if ($remaining <= 0) {
                    continue;
                }

                $availableQuantity = (int) ($availableByProduct[$item->product_id] ?? 0);

                if ($availableQuantity <= 0) {
                    continue;
                }

                $quantityToAllocate = min($availableQuantity, $remaining);
                $warehouseAllocation['items'][] = [
                    'product_id' => $item->product_id,
                    'quantity' => $quantityToAllocate,
                    'order_product_id' => $item->id,
                ];
                $remainingItems[$item->id] -= $quantityToAllocate;
                $availableByProduct[$item->product_id] -= $quantityToAllocate;
            }

            if ($warehouseAllocation['items'] !== []) {
                $allocation[] = $warehouseAllocation;
            }

            if (array_sum($remainingItems) === 0) {
                break;
            }
        }

        return $allocation;
    }

    private function validateAllocationCompleteness(
        array $warehouseAllocation,
        Collection $orderItems,
        string $city
    ): void {
        $allocatedQuantities = [];

        foreach ($warehouseAllocation as $allocation) {
            foreach ($allocation['items'] as $item) {
                $allocatedQuantities[$item['order_product_id']] =
                    ($allocatedQuantities[$item['order_product_id']] ?? 0)
                    + $item['quantity'];
            }
        }

        $unfulfilled = [];
        foreach ($orderItems as $item) {
            $allocated = (int) ($allocatedQuantities[$item->id] ?? 0);
            if ($allocated >= $item->quantity) {
                continue;
            }

            $unit = $item->stock_unit === 'gram' ? 'г' : 'шт.';
            $productName = $item->product->name ?? "Товар {$item->product_id}";
            $unfulfilled[] = sprintf(
                '%s (не хватает: %d %s)',
                $productName,
                $item->quantity - $allocated,
                $unit
            );
        }

        if ($unfulfilled !== []) {
            throw new DomainException(
                "Недостаточно товаров в городе {$city}: " . implode(', ', $unfulfilled)
            );
        }
    }

    /**
     * Создаёт складские исполнения. Основной заказ остаётся клиентским документом.
     *
     * @return array<int, Order>
     */
    public function createPartialOrders(
        Order $mainOrder,
        array $warehouseAllocation,
        AddressClient $shippingAddress
    ): array {
        $orders = [];
        $deliveryHub = $this->determineDeliveryHub(
            $shippingAddress->city,
            $warehouseAllocation
        );

        foreach ($warehouseAllocation as $index => $allocation) {
            $orders[] = $this->createPartialOrder(
                $mainOrder,
                $allocation,
                $index + 1,
                $deliveryHub
            );
        }

        $mainOrder->update([
            'warehouse_id' => $deliveryHub->id,
            'internal_notes' => trim(
                ($mainOrder->internal_notes ?? '')
                . "\nСоздано складских исполнений: " . count($orders)
                . ". Точка консолидации: {$deliveryHub->name}"
            ),
        ]);

        return $orders;
    }

    private function createPartialOrder(
        Order $mainOrder,
        array $allocation,
        int $index,
        Warehouse $deliveryHub
    ): Order {
        $partialOrder = Order::create([
            'user_id' => $mainOrder->user_id,
            'sales_channel' => $mainOrder->sales_channel
                ?: Order::SALES_CHANNEL_ONLINE,
            'status' => Order::STATUS_PENDING,
            'shipping_address_id' => $mainOrder->shipping_address_id,
            'delivery_method_id' => $mainOrder->delivery_method_id,
            'warehouse_id' => $allocation['warehouse_id'],
            'destination_warehouse_id' => $deliveryHub->id,
            'payment_method' => $mainOrder->payment_method,
            'customer_notes' => $mainOrder->customer_notes,
            'internal_notes' => "Частичный заказ #{$index} от основного заказа #{$mainOrder->id}",
            'parent_order_id' => $mainOrder->id,
            'shipping_cost' => 0,
            'confirmed_at' => now(),
        ]);

        $partialGiftIds = [];

        foreach ($allocation['items'] as $item) {
            $mainOrderItem = OrderProduct::find($item['order_product_id']);
            if (!$mainOrderItem) {
                continue;
            }

            $mainQuantity = max(1, (int) $mainOrderItem->quantity);
            $allocationRatio = $item['quantity'] / $mainQuantity;
            $partialTotal = round(
                (float) $mainOrderItem->total_price * $allocationRatio,
                2
            );

            $partialOrderGiftId = null;
            if ($mainOrderItem->order_gift_id !== null) {
                $mainGiftId = (int) $mainOrderItem->order_gift_id;
                if (!isset($partialGiftIds[$mainGiftId])) {
                    $mainGift = $mainOrder->gifts()->findOrFail($mainGiftId);
                    $partialGift = $partialOrder->gifts()->create([
                        'gift_id' => $mainGift->gift_id,
                        'client_instance_id' => $mainGift->client_instance_id,
                        'gift_version' => $mainGift->gift_version,
                        'name' => $mainGift->name,
                        'description' => $mainGift->description,
                        'quantity' => $mainGift->quantity,
                        // Наценка принадлежит только клиентскому заказу.
                        'markup_unit_amount' => 0,
                        'markup_total_amount' => 0,
                        'layout_snapshot' => $mainGift->layout_snapshot,
                    ]);
                    $partialGiftIds[$mainGiftId] = $partialGift->id;
                }
                $partialOrderGiftId = $partialGiftIds[$mainGiftId];
            }

            $partialOrder->items()->create([
                'product_id' => $item['product_id'],
                'order_gift_id' => $partialOrderGiftId,
                'product_size_id' => $mainOrderItem->product_size_id,
                'gift_item_client_id' => $mainOrderItem->gift_item_client_id,
                'gift_item_quantity' => $mainOrderItem->gift_item_quantity,
                'gift_item_sort_order' => $mainOrderItem->gift_item_sort_order,
                'quantity' => $item['quantity'],
                'stock_unit' => $mainOrderItem->stock_unit,
                'sale_step' => $mainOrderItem->sale_step,
                'price_unit_quantity' => $mainOrderItem->price_unit_quantity,
                'unit_price' => $mainOrderItem->unit_price,
                'promotion_discount_id' => $mainOrderItem->promotion_discount_id,
                'selected_discount_id' => $mainOrderItem->selected_discount_id,
                'promotion_discount_percent' => $mainOrderItem->promotion_discount_percent ?? 0,
                'personal_discount_percent' => $mainOrderItem->personal_discount_percent ?? 0,
                'promotion_discount_amount' => round(
                    (float) $mainOrderItem->promotion_discount_amount * $allocationRatio,
                    2
                ),
                'selected_discount_amount' => round(
                    (float) $mainOrderItem->selected_discount_amount * $allocationRatio,
                    2
                ),
                'final_unit_price' => $mainOrderItem->final_unit_price
                    ?? $mainOrderItem->unit_price,
                'total_price' => $partialTotal,
                'pricing_snapshot' => array_merge(
                    $mainOrderItem->pricing_snapshot ?? [],
                    [
                        'quantity' => $item['quantity'],
                        'final_total' => $partialTotal,
                    ]
                ),
            ]);
        }

        $this->recalculateOrderTotals($partialOrder);

        return $partialOrder->fresh('items');
    }

    private function determineDeliveryHub(
        string $city,
        array $warehouseAllocation
    ): Warehouse {
        $hub = Warehouse::query()
            ->where('city', $city)
            ->active()
            ->where('is_delivery_hub', true)
            ->orderByRaw(
                'CASE WHEN type = ? THEN 0 ELSE 1 END',
                [Warehouse::TYPE_WAREHOUSE]
            )
            ->orderBy('id')
            ->first();

        if ($hub) {
            return $hub;
        }

        // Совместимость с тестовыми и старыми данными до настройки хаба.
        $fallbackId = (int) ($warehouseAllocation[0]['warehouse_id'] ?? 0);
        $fallback = Warehouse::query()->active()->find($fallbackId);
        if (!$fallback) {
            throw new DomainException(
                "В городе {$city} не настроена точка консолидации доставок"
            );
        }

        return $fallback;
    }

    private function recalculateOrderTotals(Order $order): void
    {
        $productsTotal = (float) $order->items()->sum('total_price');

        $order->update([
            'products_total' => $productsTotal,
            'shipping_cost' => 0,
            'final_total' => $productsTotal,
        ]);
    }

    /**
     * @param array<int, Order> $partialOrders
     */
    public function reserveOnlineStockForOrders(
        array $partialOrders,
        ?int $actorId = null
    ): void {
        DB::transaction(function () use ($partialOrders, $actorId) {
            $orderIds = collect($partialOrders)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values();

            $orders = Order::query()
                ->whereIn('id', $orderIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('items')
                ->get();

            $requests = $orders
                ->filter(fn (Order $order) => $order->stock_reserved_at === null)
                ->flatMap(function (Order $order) {
                    if (!$order->warehouse_id) {
                        throw new DomainException('Для складского исполнения не указан склад');
                    }

                    return $order->items->map(function (OrderProduct $item) use ($order) {
                        if ((int) $item->quantity <= 0) {
                            throw new DomainException('Количество резерва должно быть положительным');
                        }

                        return [
                            'order' => $order,
                            'warehouse_id' => (int) $order->warehouse_id,
                            'product_id' => (int) $item->product_id,
                            'quantity' => (int) $item->quantity,
                        ];
                    });
                })
                ->groupBy(fn (array $request): string => implode(':', [
                    $request['order']->id,
                    $request['warehouse_id'],
                    $request['product_id'],
                ]))
                ->map(function (Collection $group): array {
                    $request = $group->first();
                    $request['quantity'] = (int) $group->sum('quantity');
                    return $request;
                })
                ->sortBy(fn (array $request) => sprintf(
                    '%020d:%020d:%020d',
                    $request['warehouse_id'],
                    $request['product_id'],
                    $request['order']->id
                ));

            foreach ($requests as $request) {
                $inventory = $this->lockInventory(
                    $request['warehouse_id'],
                    $request['product_id']
                );

                if ($inventory->availableQuantity() < $request['quantity']) {
                    throw new DomainException(sprintf(
                        'Недостаточно товара на складе. Требуется: %d, доступно: %d',
                        $request['quantity'],
                        $inventory->availableQuantity()
                    ));
                }

                $before = $this->balances($inventory);
                $inventory->reserved_online_quantity += $request['quantity'];
                $inventory->save();

                $this->recordMovement(
                    $inventory,
                    InventoryMovement::TYPE_ONLINE_RESERVE,
                    $before,
                    0,
                    $request['quantity'],
                    0,
                    $request['order'],
                    $actorId,
                    'Резерв интернет-заказа',
                    "online_reserve:order:{$request['order']->id}:inventory:{$inventory->id}"
                );
            }

            foreach ($orders as $order) {
                if ($order->stock_reserved_at === null) {
                    $order->update(['stock_reserved_at' => now()]);
                }
            }
        });
    }

    public function releaseOnlineStockForOrder(
        Order $order,
        ?int $actorId = null
    ): void {
        DB::transaction(function () use ($order, $actorId) {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($order->id);

            if ($lockedOrder->stock_released_at !== null) {
                return;
            }

            if ($lockedOrder->stock_committed_at !== null) {
                throw new DomainException('Нельзя освободить уже списанный складской резерв');
            }

            if ($lockedOrder->stock_reserved_at === null || !$lockedOrder->warehouse_id) {
                return;
            }

            foreach ($lockedOrder->items->groupBy('product_id')->sortKeys() as $productId => $items) {
                $quantity = (int) $items->sum('quantity');
                $inventory = $this->lockInventory(
                    (int) $lockedOrder->warehouse_id,
                    (int) $productId
                );

                if ($inventory->reserved_online_quantity < $quantity) {
                    throw new DomainException('Складской онлайн-резерв повреждён');
                }

                $before = $this->balances($inventory);
                $inventory->reserved_online_quantity -= $quantity;
                $inventory->save();

                $this->recordMovement(
                    $inventory,
                    InventoryMovement::TYPE_ONLINE_RELEASE,
                    $before,
                    0,
                    -$quantity,
                    0,
                    $lockedOrder,
                    $actorId,
                    'Освобождение резерва отменённого заказа',
                    "online_release:order:{$lockedOrder->id}:inventory:{$inventory->id}"
                );
            }

            $lockedOrder->update(['stock_released_at' => now()]);
        });
    }

    public function commitOnlineStockForOrder(
        Order $order,
        ?int $actorId = null
    ): void {
        DB::transaction(function () use ($order, $actorId) {
            $partialOrderIds = !$order->parent_order_id
                ? Order::query()
                    ->where('parent_order_id', $order->id)
                    ->orderBy('id')
                    ->pluck('id')
                : collect();
            $targetOrderIds = $partialOrderIds->isNotEmpty()
                ? $partialOrderIds
                : collect([$order->id]);

            $targetOrders = Order::query()
                ->whereIn('id', $targetOrderIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('items')
                ->get();

            if ($targetOrders->isEmpty()) {
                throw new DomainException(
                    'У интернет-заказа нет складских исполнений для списания'
                );
            }

            foreach ($targetOrders as $targetOrder) {
                $this->commitLockedOnlineOrder($targetOrder, $actorId);
            }

            if ($partialOrderIds->isNotEmpty()) {
                Order::query()
                    ->whereKey($order->id)
                    ->whereNull('stock_committed_at')
                    ->update(['stock_committed_at' => now()]);
            }
        });
    }

    private function commitLockedOnlineOrder(
        Order $order,
        ?int $actorId
    ): void {
        if ($order->stock_committed_at !== null) {
            return;
        }

        if ($order->stock_released_at !== null) {
            throw new DomainException('Нельзя списать освобождённый складской резерв');
        }

        if ($order->stock_reserved_at === null || !$order->warehouse_id) {
            throw new DomainException('У заказа отсутствует складской резерв');
        }

        foreach ($order->items->groupBy('product_id')->sortKeys() as $productId => $items) {
            $quantity = (int) $items->sum('quantity');
            $inventory = $this->lockInventory(
                (int) $order->warehouse_id,
                (int) $productId
            );

            if ($inventory->reserved_online_quantity < $quantity) {
                throw new DomainException('Складской онлайн-резерв повреждён');
            }

            if ($inventory->quantity < $quantity) {
                throw new DomainException(
                    'Физического остатка недостаточно для отгрузки заказа'
                );
            }

            $before = $this->balances($inventory);
            $inventory->quantity -= $quantity;
            $inventory->reserved_online_quantity -= $quantity;
            $inventory->save();

            $returnCycle = InventoryMovement::query()
                ->where('order_id', $order->id)
                ->where('inventory_id', $inventory->id)
                ->where('type', InventoryMovement::TYPE_ONLINE_RETURN)
                ->count();
            $cycleSuffix = $returnCycle > 0
                ? ":cycle:{$returnCycle}"
                : '';

            $this->recordMovement(
                $inventory,
                InventoryMovement::TYPE_ONLINE_SALE,
                $before,
                -$quantity,
                -$quantity,
                0,
                $order,
                $actorId,
                'Отгрузка интернет-заказа',
                "online_sale:order:{$order->id}:inventory:{$inventory->id}{$cycleSuffix}"
            );
        }

        $order->update(['stock_committed_at' => now()]);
    }

    public function restoreCommittedOnlineStockForOrder(
        Order $order,
        int $actorId,
        string $reason
    ): void {
        DB::transaction(function () use ($order, $actorId, $reason): void {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($order->id);

            if ($lockedOrder->stock_committed_at === null) {
                return;
            }
            if (!$lockedOrder->warehouse_id || !$lockedOrder->parent_order_id) {
                throw new DomainException(
                    'Возврат выполняется только для складской части заказа'
                );
            }
            $reason = trim($reason);
            if ($reason === '') {
                throw new DomainException('Для возврата на склад нужна причина');
            }

            foreach ($lockedOrder->items->groupBy('product_id')->sortKeys() as $productId => $items) {
                $quantity = (int) $items->sum('quantity');
                $inventory = $this->lockInventory(
                    (int) $lockedOrder->warehouse_id,
                    (int) $productId
                );
                $returnCycle = InventoryMovement::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('inventory_id', $inventory->id)
                    ->where('type', InventoryMovement::TYPE_ONLINE_RETURN)
                    ->count() + 1;
                $before = $this->balances($inventory);
                $inventory->quantity += $quantity;
                $inventory->reserved_online_quantity += $quantity;
                $inventory->save();

                $this->recordMovement(
                    $inventory,
                    InventoryMovement::TYPE_ONLINE_RETURN,
                    $before,
                    $quantity,
                    $quantity,
                    0,
                    $lockedOrder,
                    $actorId,
                    $reason,
                    "online_return:order:{$lockedOrder->id}:inventory:{$inventory->id}:cycle:{$returnCycle}"
                );
            }

            $lockedOrder->update(['stock_committed_at' => null]);
        });
    }

    /**
     * Синхронизирует резерв продавца с полной новой версией его заказа.
     * Увеличение резерва допускает дефицит: физическая продажа могла уже
     * состояться офлайн, а окончательное решение принимается при завершении.
     *
     * @param array<int, int> $oldQuantities
     * @param array<int, int> $newQuantities
     */
    public function syncSellerReservation(
        Order $order,
        array $oldQuantities,
        array $newQuantities,
        int $actorId,
        int $revision
    ): void {
        DB::transaction(function () use (
            $order,
            $oldQuantities,
            $newQuantities,
            $actorId,
            $revision
        ): void {
            $productIds = collect(array_keys($oldQuantities))
                ->merge(array_keys($newQuantities))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            foreach ($productIds as $productId) {
                $oldQuantity = (int) ($oldQuantities[$productId] ?? 0);
                $newQuantity = (int) ($newQuantities[$productId] ?? 0);
                $delta = $newQuantity - $oldQuantity;
                if ($delta === 0) {
                    continue;
                }

                $inventory = Inventory::query()
                    ->where('warehouse_id', $order->warehouse_id)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();
                if (!$inventory) {
                    throw new DomainException(
                        "Товар {$productId} не числится в выбранной рабочей точке"
                    );
                }
                if ((int) $inventory->reserved_seller_quantity + $delta < 0) {
                    throw new DomainException('Складской резерв продавца повреждён');
                }

                $before = $this->balances($inventory);
                $inventory->reserved_seller_quantity += $delta;
                $inventory->save();

                $this->recordMovement(
                    $inventory,
                    $delta > 0
                        ? InventoryMovement::TYPE_SELLER_RESERVE
                        : InventoryMovement::TYPE_SELLER_RELEASE,
                    $before,
                    0,
                    0,
                    $delta,
                    $order,
                    $actorId,
                    $delta > 0
                        ? 'Резерв офлайн-продажи'
                        : 'Уменьшение резерва после исправления офлайн-продажи',
                    "seller_sync:order:{$order->id}:revision:{$revision}:inventory:{$inventory->id}",
                    ['seller_revision' => $revision]
                );
            }

            if ($order->stock_reserved_at === null) {
                $order->update(['stock_reserved_at' => now()]);
            }
        });
    }

    public function releaseSellerStockForOrder(Order $order, int $actorId): void
    {
        DB::transaction(function () use ($order, $actorId): void {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($order->id);

            if ($lockedOrder->stock_released_at !== null) {
                return;
            }
            if ($lockedOrder->stock_committed_at !== null) {
                throw new DomainException('Нельзя отменить уже проведённую продажу');
            }

            foreach ($lockedOrder->items->sortBy('product_id') as $item) {
                $inventory = $this->lockInventory(
                    (int) $lockedOrder->warehouse_id,
                    (int) $item->product_id
                );
                if ((int) $inventory->reserved_seller_quantity < (int) $item->quantity) {
                    throw new DomainException('Складской резерв продавца повреждён');
                }

                $before = $this->balances($inventory);
                $inventory->reserved_seller_quantity -= (int) $item->quantity;
                $inventory->save();

                $this->recordMovement(
                    $inventory,
                    InventoryMovement::TYPE_SELLER_RELEASE,
                    $before,
                    0,
                    0,
                    -(int) $item->quantity,
                    $lockedOrder,
                    $actorId,
                    'Отмена офлайн-продажи продавцом',
                    "seller_cancel:order:{$lockedOrder->id}:inventory:{$inventory->id}"
                );
            }

            $lockedOrder->update(['stock_released_at' => now()]);
        });
    }

    /**
     * @return array{committed:bool, conflicts:array<int, array<string, int|string>>}
     */
    public function completeSellerStockForOrder(
        Order $order,
        int $actorId,
        bool $allowConflicts
    ): array {
        return DB::transaction(function () use ($order, $actorId, $allowConflicts): array {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($order->id);

            if ($lockedOrder->stock_committed_at !== null) {
                return ['committed' => true, 'conflicts' => []];
            }
            if ($lockedOrder->stock_released_at !== null) {
                throw new DomainException('Нельзя провести освобождённый резерв продавца');
            }
            if ($lockedOrder->stock_reserved_at === null || !$lockedOrder->warehouse_id) {
                throw new DomainException('У заказа продавца отсутствует складской резерв');
            }

            $inventories = [];
            foreach ($lockedOrder->items->sortBy('product_id') as $item) {
                $inventory = $this->lockInventory(
                    (int) $lockedOrder->warehouse_id,
                    (int) $item->product_id
                );
                if ((int) $inventory->reserved_seller_quantity < (int) $item->quantity) {
                    throw new DomainException('Складской резерв продавца повреждён');
                }
                $inventories[(int) $item->product_id] = $inventory;
            }

            $conflicts = [];
            foreach ($lockedOrder->items->sortBy('product_id') as $item) {
                /** @var Inventory $inventory */
                $inventory = $inventories[(int) $item->product_id];
                $quantity = (int) $item->quantity;
                $physicalBefore = (int) $inventory->quantity;
                $onlineBefore = (int) $inventory->reserved_online_quantity;
                $sellerBefore = (int) $inventory->reserved_seller_quantity;
                $physicalDiscrepancy = max(0, $quantity - $physicalBefore);
                $physicalAfter = max(0, $physicalBefore - $quantity);
                $onlineShortageBefore = max(0, $onlineBefore - $physicalBefore);
                $onlineShortageAfter = max(0, $onlineBefore - $physicalAfter);
                $newOnlineShortage = max(0, $onlineShortageAfter - $onlineShortageBefore);

                if ($newOnlineShortage > 0) {
                    $conflicts[] = [
                        'product_id' => (int) $item->product_id,
                        'warehouse_id' => (int) $lockedOrder->warehouse_id,
                        'reason' => 'online_reservation_conflict',
                        'shortage_quantity' => $newOnlineShortage,
                        'reserved_online_before' => $onlineBefore,
                        'reserved_seller_before' => $sellerBefore,
                        'requested_quantity' => $quantity,
                        'physical_quantity_before' => $physicalBefore,
                    ];
                }
                if ($physicalDiscrepancy > 0) {
                    $conflicts[] = [
                        'product_id' => (int) $item->product_id,
                        'warehouse_id' => (int) $lockedOrder->warehouse_id,
                        'reason' => 'physical_stock_discrepancy',
                        'shortage_quantity' => $physicalDiscrepancy,
                        'reserved_online_before' => $onlineBefore,
                        'reserved_seller_before' => $sellerBefore,
                        'requested_quantity' => $quantity,
                        'physical_quantity_before' => $physicalBefore,
                    ];
                }
            }

            if ($conflicts !== [] && !$allowConflicts) {
                return ['committed' => false, 'conflicts' => $conflicts];
            }

            foreach ($lockedOrder->items->sortBy('product_id') as $item) {
                /** @var Inventory $inventory */
                $inventory = $inventories[(int) $item->product_id];
                $requestedQuantity = (int) $item->quantity;
                $physicalDeduction = min((int) $inventory->quantity, $requestedQuantity);
                $before = $this->balances($inventory);
                $inventory->quantity -= $physicalDeduction;
                $inventory->reserved_seller_quantity -= $requestedQuantity;
                $inventory->save();

                $this->recordMovement(
                    $inventory,
                    InventoryMovement::TYPE_SELLER_SALE,
                    $before,
                    -$physicalDeduction,
                    0,
                    -$requestedQuantity,
                    $lockedOrder,
                    $actorId,
                    'Проведение физической продажи',
                    "seller_sale:order:{$lockedOrder->id}:inventory:{$inventory->id}",
                    [
                        'requested_quantity' => $requestedQuantity,
                        'unaccounted_quantity' => $requestedQuantity - $physicalDeduction,
                        'accepted_with_conflicts' => $conflicts !== [],
                    ]
                );
            }

            $lockedOrder->update(['stock_committed_at' => now()]);

            return ['committed' => true, 'conflicts' => $conflicts];
        });
    }

    public function adjustQuantity(
        Inventory $inventory,
        int $newQuantity,
        string $reason,
        ?int $actorId = null
    ): Inventory {
        if ($newQuantity < 0) {
            throw new DomainException('Физический остаток не может быть отрицательным');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Для корректировки остатка нужна причина');
        }

        return DB::transaction(function () use (
            $inventory,
            $newQuantity,
            $reason,
            $actorId
        ) {
            $lockedInventory = Inventory::query()
                ->lockForUpdate()
                ->findOrFail($inventory->id);

            if ((int) $lockedInventory->quantity === $newQuantity) {
                return $lockedInventory;
            }

            $before = $this->balances($lockedInventory);
            $delta = $newQuantity - (int) $lockedInventory->quantity;
            $lockedInventory->quantity = $newQuantity;
            $lockedInventory->save();

            $this->recordMovement(
                $lockedInventory,
                InventoryMovement::TYPE_ADJUSTMENT,
                $before,
                $delta,
                0,
                0,
                null,
                $actorId,
                $reason,
                'adjustment:' . Str::uuid()
            );

            return $lockedInventory->fresh();
        });
    }

    public function recordOpeningBalance(
        Inventory $inventory,
        ?int $actorId = null
    ): void {
        $before = [
            'physical' => 0,
            'reserved_online' => 0,
            'reserved_seller' => 0,
        ];

        $this->recordMovement(
            $inventory,
            InventoryMovement::TYPE_OPENING_BALANCE,
            $before,
            (int) $inventory->quantity,
            0,
            0,
            null,
            $actorId,
            'Начальный остаток',
            "opening_balance:inventory:{$inventory->id}"
        );
    }

    private function lockInventory(int $warehouseId, int $productId): Inventory
    {
        return Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function balances(Inventory $inventory): array
    {
        return [
            'physical' => (int) $inventory->quantity,
            'reserved_online' => (int) $inventory->reserved_online_quantity,
            'reserved_seller' => (int) $inventory->reserved_seller_quantity,
        ];
    }

    private function recordMovement(
        Inventory $inventory,
        string $type,
        array $before,
        int $physicalDelta,
        int $onlineDelta,
        int $sellerDelta,
        ?Order $order,
        ?int $actorId,
        ?string $reason,
        string $idempotencyKey,
        array $metadata = []
    ): void {
        InventoryMovement::create([
            'inventory_id' => $inventory->id,
            'product_id' => $inventory->product_id,
            'warehouse_id' => $inventory->warehouse_id,
            'order_id' => $order?->id,
            'actor_id' => $actorId,
            'type' => $type,
            'physical_delta' => $physicalDelta,
            'reserved_online_delta' => $onlineDelta,
            'reserved_seller_delta' => $sellerDelta,
            'physical_before' => $before['physical'],
            'physical_after' => (int) $inventory->quantity,
            'reserved_online_before' => $before['reserved_online'],
            'reserved_online_after' => (int) $inventory->reserved_online_quantity,
            'reserved_seller_before' => $before['reserved_seller'],
            'reserved_seller_after' => (int) $inventory->reserved_seller_quantity,
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
            'metadata' => array_merge([
                'product_name' => $inventory->product?->name,
                'warehouse_name' => $inventory->warehouse?->name,
                'warehouse_type' => $inventory->warehouse?->type,
                'available_before' => max(
                    0,
                    $before['physical']
                        - $before['reserved_online']
                        - $before['reserved_seller']
                ),
                'available_after' => $inventory->availableQuantity(),
                'shortage_after' => $inventory->shortageQuantity(),
            ], $metadata),
        ]);
    }
}
