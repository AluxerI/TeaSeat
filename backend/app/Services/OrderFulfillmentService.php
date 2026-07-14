<?php

namespace App\Services;

use App\Models\FulfillmentIssue;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    public function __construct(
        protected PickerAccessService $accessService,
        protected WarehouseService $warehouseService,
        protected ManagerAccessService $managerAccessService
    ) {
    }

    public function pickingOrders(User $picker, array $filters): LengthAwarePaginator
    {
        $query = $this->accessService->scopeWarehouses(
            $this->basePickingQuery(),
            $picker,
            'orders.warehouse_id'
        );

        if ($this->accessService->isAdmin($picker)) {
            $query->whereIn('orders.status', [
                Order::STATUS_CONFIRMED,
                Order::STATUS_PROCESSING,
            ]);
        } else {
            $query->where(function (Builder $builder) use ($picker): void {
                $builder->where(function (Builder $available): void {
                    $available->where('orders.status', Order::STATUS_CONFIRMED)
                        ->whereNull('orders.picker_id');
                })
                    ->orWhere(function (Builder $owned) use ($picker): void {
                        $owned->where('orders.status', Order::STATUS_PROCESSING)
                            ->where('orders.picker_id', $picker->id);
                    });
            });
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('orders.status', $filters['status']);
        }
        if (($filters['warehouse_id'] ?? null) !== null) {
            $query->where('orders.warehouse_id', (int) $filters['warehouse_id']);
        }
        if (filter_var($filters['mine'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->where('orders.picker_id', $picker->id);
        }
        if (($filters['job_type'] ?? null) === 'source') {
            $query->whereNotNull('orders.parent_order_id');
        } elseif (($filters['job_type'] ?? null) === 'consolidation') {
            $query->whereNull('orders.parent_order_id');
        }

        return $query
            ->orderByRaw("CASE orders.status WHEN 'processing' THEN 0 ELSE 1 END")
            ->orderBy('orders.created_at')
            ->orderBy('orders.id')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    public function findAccessible(User $picker, int $orderId): Order
    {
        $query = $this->accessService->scopeWarehouses(
            $this->basePickingQuery(),
            $picker,
            'orders.warehouse_id'
        );
        if (!$this->accessService->isAdmin($picker)) {
            $query->where(function (Builder $builder) use ($picker): void {
                $builder->where(function (Builder $available): void {
                    $available->where('orders.status', Order::STATUS_CONFIRMED)
                        ->whereNull('orders.picker_id');
                })->orWhere('orders.picker_id', $picker->id);
            });
        }

        return $query->findOrFail($orderId);
    }

    public function incomingTransfers(
        User $picker,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->accessService->scopeWarehouses(
            Order::query()
                ->where('status', Order::STATUS_AWAITING_RECEIPT)
                ->whereNotNull('parent_order_id')
                ->whereColumn('warehouse_id', '!=', 'destination_warehouse_id')
                ->with($this->relations()),
            $picker,
            'orders.destination_warehouse_id'
        )
            ->orderBy('courier_arrived_at')
            ->orderBy('id')
            ->paginate(min(100, max(1, $perPage)));
    }

    public function receiveTransfer(User $picker, int $orderId): Order
    {
        return DB::transaction(function () use ($picker, $orderId): Order {
            $order = $this->lockOrderWithParentFirst(
                $orderId,
                $this->relations()
            );
            $this->accessService->assertWarehouse(
                $picker,
                (int) $order->destination_warehouse_id
            );
            if (!$order->isWarehouseTransfer()) {
                throw new DomainException('Заказ не является межскладским перемещением');
            }
            if ($order->status === Order::STATUS_DELIVERED
                && $order->received_at !== null) {
                return $order;
            }
            if ($order->status !== Order::STATUS_AWAITING_RECEIPT) {
                throw new DomainException('Перемещение ещё не ожидает приёмки');
            }

            $order->update([
                'status' => Order::STATUS_DELIVERED,
                'received_at' => now(),
                'delivered_at' => now(),
            ]);
            $this->recordStatus(
                $order,
                Order::STATUS_AWAITING_RECEIPT,
                Order::STATUS_DELIVERED,
                $picker->id,
                'Сборщик подтвердил получение упакованной части заказа'
            );
            $this->syncMainOrderAfterArrival(
                (int) $order->parent_order_id,
                $picker->id
            );

            return $order->fresh($this->relations());
        });
    }

    public function take(User $picker, int $orderId): Order
    {
        return $this->transition($picker, $orderId, function (Order $order) use ($picker): void {
            $this->assertPickingJob($order);
            if ($order->status === Order::STATUS_PROCESSING) {
                if ((int) $order->picker_id === (int) $picker->id) {
                    return;
                }

                throw new DomainException('Заказ уже собирает другой сотрудник');
            }
            if ($order->status !== Order::STATUS_CONFIRMED) {
                throw new DomainException('Заказ пока нельзя взять в сборку');
            }

            $oldStatus = $order->status;
            $order->update([
                'status' => Order::STATUS_PROCESSING,
                'picker_id' => $picker->id,
                'picking_started_at' => now(),
            ]);
            $this->recordStatus(
                $order,
                $oldStatus,
                Order::STATUS_PROCESSING,
                $picker->id,
                'Сборщик взял заказ в работу'
            );
        });
    }

    public function release(User $picker, int $orderId): Order
    {
        return $this->transition($picker, $orderId, function (Order $order) use ($picker): void {
            $this->assertPickingJob($order);
            if ($order->status === Order::STATUS_CONFIRMED && $order->picker_id === null) {
                return;
            }
            $this->assertOwnedProcessing($picker, $order);

            $order->update([
                'status' => Order::STATUS_CONFIRMED,
                'picker_id' => null,
                'picking_started_at' => null,
            ]);
            $this->recordStatus(
                $order,
                Order::STATUS_PROCESSING,
                Order::STATUS_CONFIRMED,
                $picker->id,
                'Сборщик отказался от сборки'
            );
        });
    }

    public function complete(User $picker, int $orderId): Order
    {
        return $this->transition($picker, $orderId, function (Order $order) use ($picker): void {
            if (in_array($order->status, [
                Order::STATUS_READY_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
            ], true) && (int) $order->picker_id === (int) $picker->id) {
                return;
            }
            $this->assertPickingJob($order);
            $this->assertOwnedProcessing($picker, $order);

            if ($order->parent_order_id) {
                // Здесь товар физически покидает доступный остаток склада.
                $this->warehouseService->commitOnlineStockForOrder(
                    $order,
                    $picker->id
                );

                $targetStatus = $order->isWarehouseTransfer()
                    ? Order::STATUS_READY_FOR_DELIVERY
                    : Order::STATUS_DELIVERED;
                $updates = [
                    'status' => $targetStatus,
                    'ready_for_delivery_at' => now(),
                ];
                if ($targetStatus === Order::STATUS_DELIVERED) {
                    $updates['delivered_at'] = now();
                }
                $order->update($updates);
                $this->recordStatus(
                    $order,
                    Order::STATUS_PROCESSING,
                    $targetStatus,
                    $picker->id,
                    $order->isWarehouseTransfer()
                        ? 'Часть заказа упакована для перемещения в точку консолидации'
                        : 'Заказ упакован в точке консолидации'
                );
                $this->syncMainOrderAfterArrival(
                    $order->parent_order_id,
                    $picker->id
                );

                return;
            }

            // Основной заказ содержит уже списанные и доставленные в хаб
            // упаковки. Повторное складское списание здесь запрещено.
            $order->update([
                'status' => Order::STATUS_READY_FOR_DELIVERY,
                'ready_for_delivery_at' => now(),
            ]);
            $this->recordStatus(
                $order,
                Order::STATUS_PROCESSING,
                Order::STATUS_READY_FOR_DELIVERY,
                $picker->id,
                'Сборщик объединил складские части в клиентский заказ'
            );
        });
    }

    public function escalate(
        User $picker,
        int $orderId,
        string $comment
    ): Order {
        return $this->transition($picker, $orderId, function (Order $order) use ($picker, $comment): void {
            $this->assertOwnedProcessing($picker, $order);
            $comment = trim($comment);
            if ($comment === '') {
                throw new DomainException('Для передачи менеджеру нужен комментарий');
            }

            $order->update([
                'status' => Order::STATUS_MANAGER_REVIEW,
                'picker_id' => null,
                'picking_started_at' => null,
                'internal_notes' => trim(
                    ($order->internal_notes ?? '')
                    . "\nСборщик {$picker->id}: {$comment}"
                ),
            ]);
            $this->recordStatus(
                $order,
                Order::STATUS_PROCESSING,
                Order::STATUS_MANAGER_REVIEW,
                $picker->id,
                $comment
            );
            $this->markMainOrderForReview($order, $picker->id, $comment);
        });
    }

    public function reportShortage(
        User $picker,
        int $orderId,
        int $productId,
        int $shortageQuantity,
        string $comment
    ): array {
        return DB::transaction(function () use (
            $picker,
            $orderId,
            $productId,
            $shortageQuantity,
            $comment
        ): array {
            $order = $this->lockOrderWithParentFirst(
                $orderId,
                ['items', 'warehouse']
            );
            $this->accessService->assertWarehouse(
                $picker,
                (int) $order->warehouse_id
            );
            $this->assertOwnedProcessing($picker, $order);
            if (!$order->parent_order_id) {
                throw new DomainException(
                    'Недостача фиксируется на складской части, а не на консолидации'
                );
            }
            $orderItem = $order->items->first(
                fn ($item): bool => (int) $item->product_id === $productId
            );
            if (!$orderItem) {
                throw new DomainException('Товар не входит в это складское исполнение');
            }
            if ($shortageQuantity <= 0
                || $shortageQuantity > (int) $orderItem->quantity) {
                throw new DomainException(
                    'Недостача должна быть положительной и не больше количества позиции'
                );
            }

            $inventory = Inventory::query()
                ->where('warehouse_id', $order->warehouse_id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $issue = FulfillmentIssue::query()
                ->where('source_order_id', $order->id)
                ->where('product_id', $productId)
                ->where(
                    'reason',
                    FulfillmentIssue::REASON_PHYSICAL_STOCK_DISCREPANCY
                )
                ->lockForUpdate()
                ->first();
            if ($issue && $issue->status !== FulfillmentIssue::STATUS_WAITING) {
                throw new DomainException(
                    'Предыдущее дело по этой недостаче уже рассматривается или закрыто'
                );
            }

            $values = [
                'warehouse_id' => $order->warehouse_id,
                'shortage_quantity' => $shortageQuantity,
                'reserved_online_before' => (int) $inventory->reserved_online_quantity,
                'reserved_seller_before' => (int) $inventory->reserved_seller_quantity,
                'status' => FulfillmentIssue::STATUS_WAITING,
                'manager_id' => null,
            ];
            if ($issue) {
                $issue->update($values);
            } else {
                $issue = FulfillmentIssue::query()->create($values + [
                    'source_order_id' => $order->id,
                    'product_id' => $productId,
                    'reason' => FulfillmentIssue::REASON_PHYSICAL_STOCK_DISCREPANCY,
                ]);
            }

            $comment = trim($comment);
            $order->update([
                'status' => Order::STATUS_MANAGER_REVIEW,
                'picker_id' => null,
                'picking_started_at' => null,
                'internal_notes' => trim(
                    ($order->internal_notes ?? '')
                    . "\nНедостача при сборке: {$comment}"
                ),
            ]);
            $this->recordStatus(
                $order,
                Order::STATUS_PROCESSING,
                Order::STATUS_MANAGER_REVIEW,
                $picker->id,
                $comment !== '' ? $comment : 'Зафиксирована недостача при сборке'
            );
            $this->markMainOrderForReview(
                $order,
                $picker->id,
                $comment !== '' ? $comment : 'Зафиксирована недостача при сборке'
            );

            return [
                'order' => $order->fresh($this->relations()),
                'issue' => $issue->fresh(),
            ];
        });
    }

    private function syncMainOrderAfterArrival(int $mainOrderId, int $actorId): void
    {
        $main = Order::query()->lockForUpdate()->findOrFail($mainOrderId);
        $parts = Order::query()
            ->where('parent_order_id', $main->id)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->orderBy('id')
            ->get();

        if ($parts->isEmpty()
            || $parts->contains(fn (Order $part): bool =>
                $part->status !== Order::STATUS_DELIVERED)) {
            return;
        }

        if ($parts->every(fn (Order $part): bool =>
            $part->stock_committed_at !== null)
            && $main->stock_committed_at === null) {
            $main->update(['stock_committed_at' => now()]);
        }

        if ($parts->count() === 1
            && $main->status === Order::STATUS_CONFIRMED) {
            $main->update([
                'status' => Order::STATUS_READY_FOR_DELIVERY,
                'ready_for_delivery_at' => now(),
            ]);
            $this->recordStatus(
                $main,
                Order::STATUS_CONFIRMED,
                Order::STATUS_READY_FOR_DELIVERY,
                $actorId,
                'Единственная складская часть готова к клиентской доставке'
            );
        }
    }

    public function returnPackedOrderToStock(
        User $manager,
        int $orderId,
        string $reason
    ): Order {
        return DB::transaction(function () use ($manager, $orderId, $reason): Order {
            $requestedOrder = $this->lockOrderWithParentFirst(
                $orderId,
                ['partialOrders']
            );
            $this->managerAccessService->assertWarehouseAccess(
                $manager,
                (int) $requestedOrder->warehouse_id
            );

            if ($requestedOrder->parent_order_id) {
                $main = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($requestedOrder->parent_order_id);
                $part = Order::query()
                    ->lockForUpdate()
                    ->with('items')
                    ->findOrFail($requestedOrder->id);
            } else {
                $activeParts = $requestedOrder->partialOrders
                    ->where('status', '!=', Order::STATUS_CANCELLED)
                    ->values();
                if ($activeParts->count() !== 1) {
                    throw new DomainException(
                        'Составной заказ нельзя автоматически вернуть на склад после консолидации'
                    );
                }
                $main = $requestedOrder;
                $part = Order::query()
                    ->lockForUpdate()
                    ->with('items')
                    ->findOrFail($activeParts->first()->id);
            }

            $safeAtSource = (!$part->isWarehouseTransfer()
                    && $part->status === Order::STATUS_DELIVERED)
                || ($part->isWarehouseTransfer()
                    && $part->status === Order::STATUS_READY_FOR_DELIVERY);
            $mainStillAtWarehouse = in_array($main->status, [
                Order::STATUS_CONFIRMED,
                Order::STATUS_READY_FOR_DELIVERY,
            ], true);
            if (!$safeAtSource
                || !$mainStillAtWarehouse
                || $part->shipped_at !== null) {
                throw new DomainException(
                    'Автоматический возврат возможен только пока упаковка находится на исходной точке'
                );
            }

            $this->warehouseService->restoreCommittedOnlineStockForOrder(
                $part,
                $manager->id,
                $reason
            );
            $oldStatus = $part->status;
            $part->update([
                'status' => Order::STATUS_CONFIRMED,
                'picker_id' => null,
                'courier_id' => null,
                'picking_started_at' => null,
                'ready_for_delivery_at' => null,
                'courier_assigned_at' => null,
                'courier_arrived_at' => null,
                'received_at' => null,
                'shipped_at' => null,
                'delivered_at' => null,
            ]);
            $this->recordStatus(
                $part,
                $oldStatus,
                Order::STATUS_CONFIRMED,
                $manager->id,
                $reason
            );

            $mainOldStatus = $main->status;
            $main->update([
                'status' => Order::STATUS_CONFIRMED,
                'stock_committed_at' => null,
                'picker_id' => null,
                'courier_id' => null,
                'picking_started_at' => null,
                'ready_for_delivery_at' => null,
                'courier_assigned_at' => null,
                'shipped_at' => null,
                'delivered_at' => null,
            ]);
            if ($mainOldStatus !== Order::STATUS_CONFIRMED) {
                $this->recordStatus(
                    $main,
                    $mainOldStatus,
                    Order::STATUS_CONFIRMED,
                    $manager->id,
                    $reason
                );
            }

            return $main->fresh($this->relations());
        });
    }

    private function transition(
        User $picker,
        int $orderId,
        callable $callback
    ): Order {
        return DB::transaction(function () use ($picker, $orderId, $callback): Order {
            $order = $this->lockOrderWithParentFirst(
                $orderId,
                $this->relations()
            );
            $this->accessService->assertWarehouse(
                $picker,
                (int) $order->warehouse_id
            );
            $callback($order);

            return $order->fresh($this->relations());
        });
    }

    /**
     * Все workflow складской части блокируют строки в одном
     * порядке: основной заказ, затем часть. Это не даёт двум
     * сборщикам создать циклическую блокировку разных частей.
     *
     * @param array<int, string> $relations
     */
    private function lockOrderWithParentFirst(
        int $orderId,
        array $relations = []
    ): Order {
        $reference = Order::query()
            ->select(['id', 'parent_order_id'])
            ->findOrFail($orderId);

        if ($reference->parent_order_id) {
            Order::query()
                ->whereKey($reference->parent_order_id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return Order::query()
            ->lockForUpdate()
            ->with($relations)
            ->findOrFail($orderId);
    }

    private function assertPickingJob(Order $order): void
    {
        if ($order->sales_channel !== Order::SALES_CHANNEL_ONLINE
            || $order->is_supplier_order) {
            throw new DomainException('Сборщик работает только с интернет-заказами');
        }

        if ($order->parent_order_id) {
            if ($order->parentOrder?->status !== Order::STATUS_CONFIRMED) {
                throw new DomainException(
                    'Основной заказ приостановлен и пока недоступен для сборки'
                );
            }
            return;
        }

        $parts = $order->partialOrders()
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->get(['id', 'status']);
        if ($parts->count() < 2
            || $parts->contains(fn (Order $part): bool =>
                $part->status !== Order::STATUS_DELIVERED)) {
            throw new DomainException(
                'Части заказа ещё не готовы к итоговой консолидации'
            );
        }
    }

    private function assertOwnedProcessing(User $picker, Order $order): void
    {
        if ($order->status !== Order::STATUS_PROCESSING
            || (int) $order->picker_id !== (int) $picker->id) {
            throw new DomainException('Заказ не находится в сборке этого сотрудника');
        }
    }

    private function markMainOrderForReview(
        Order $order,
        int $actorId,
        string $comment
    ): void {
        if (!$order->parent_order_id) {
            return;
        }

        $main = Order::query()
            ->lockForUpdate()
            ->findOrFail($order->parent_order_id);
        if (in_array($main->status, [
            Order::STATUS_MANAGER_REVIEW,
            Order::STATUS_CANCELLED,
            Order::STATUS_DELIVERED,
        ], true)) {
            return;
        }

        $oldStatus = $main->status;
        $main->update(['status' => Order::STATUS_MANAGER_REVIEW]);
        $this->recordStatus(
            $main,
            $oldStatus,
            Order::STATUS_MANAGER_REVIEW,
            $actorId,
            $comment
        );
    }

    private function basePickingQuery(): Builder
    {
        return Order::query()
            ->where('orders.sales_channel', Order::SALES_CHANNEL_ONLINE)
            ->where('orders.is_supplier_order', false)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $source): void {
                    $source->whereNotNull('orders.parent_order_id')
                        ->whereHas('parentOrder', fn (Builder $parent) =>
                            $parent->where('status', Order::STATUS_CONFIRMED));
                })
                    ->orWhere(function (Builder $main): void {
                        $main->whereNull('orders.parent_order_id')
                            ->whereHas('partialOrders', fn (Builder $parts) =>
                                $parts->where('status', '!=', Order::STATUS_CANCELLED))
                            ->whereDoesntHave('partialOrders', fn (Builder $parts) =>
                                $parts->whereNotIn('status', [
                                    Order::STATUS_DELIVERED,
                                    Order::STATUS_CANCELLED,
                                ]));
                    });
            })
            ->with($this->relations());
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return [
            'items.product',
            'warehouse',
            'destinationWarehouse',
            'picker',
            'parentOrder.user',
            'parentOrder.shippingAddress',
            'parentOrder.deliveryMethod',
            'partialOrders',
            'deliveryMethod',
            'shippingAddress',
            'user',
        ];
    }

    private function recordStatus(
        Order $order,
        string $from,
        string $to,
        int $actorId,
        string $notes
    ): void {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actorId,
            'notes' => $notes,
        ]);
    }
}
