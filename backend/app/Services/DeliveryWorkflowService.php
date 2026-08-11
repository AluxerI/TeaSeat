<?php

namespace App\Services;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DeliveryWorkflowService
{
    public function __construct(
        protected CourierAccessService $accessService,
        protected ManagerAccessService $managerAccessService,
        protected DeliveryScheduleService $deliveryScheduleService
    ) {
    }

    public function deliveries(User $courier, array $filters): LengthAwarePaginator
    {
        $query = $this->accessService->scopeDeliveries(
            $this->baseDeliveryQuery(),
            $courier
        );

        if (!$this->accessService->isAdmin($courier)) {
            $query->where(function (Builder $builder) use ($courier): void {
                $builder->where(function (Builder $available): void {
                    $available->where('orders.status', Order::STATUS_READY_FOR_DELIVERY)
                        ->whereNull('orders.courier_id');
                    $this->deliveryScheduleService
                        ->applyCourierClaimWindow($available);
                })->orWhere('orders.courier_id', $courier->id);
            });
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('orders.status', $filters['status']);
        } else {
            $query->whereIn('orders.status', [
                Order::STATUS_READY_FOR_DELIVERY,
                Order::STATUS_SHIPPED,
                Order::STATUS_AWAITING_RECEIPT,
            ]);
        }
        if (($filters['warehouse_id'] ?? null) !== null) {
            $query->where('orders.warehouse_id', (int) $filters['warehouse_id']);
        }
        if (filter_var($filters['mine'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->where('orders.courier_id', $courier->id);
        }
        if (($filters['delivery_kind'] ?? null) === 'transfer') {
            $query->whereNotNull('orders.parent_order_id');
        } elseif (($filters['delivery_kind'] ?? null) === 'customer') {
            $query->whereNull('orders.parent_order_id');
        }

        return $query
            ->orderByRaw('CASE WHEN orders.courier_id = ? THEN 0 ELSE 1 END', [$courier->id])
            ->orderByRaw("CASE orders.status WHEN 'shipped' THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN orders.scheduled_delivery_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('orders.scheduled_delivery_date')
            ->orderBy('orders.delivery_time_from')
            ->orderBy('orders.ready_for_delivery_at')
            ->orderBy('orders.id')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    public function findAccessible(User $courier, int $orderId): Order
    {
        $query = $this->accessService->scopeDeliveries(
            $this->baseDeliveryQuery(),
            $courier
        );
        if (!$this->accessService->isAdmin($courier)) {
            $query->where(function (Builder $builder) use ($courier): void {
                $builder->where(function (Builder $available): void {
                    $available
                        ->where('orders.status', Order::STATUS_READY_FOR_DELIVERY)
                        ->whereNull('orders.courier_id');
                    $this->deliveryScheduleService
                        ->applyCourierClaimWindow($available);
                })->orWhere('orders.courier_id', $courier->id);
            });
        }

        return $query->findOrFail($orderId);
    }

    public function claim(User $courier, int $orderId): Order
    {
        return $this->transition($courier, $orderId, function (Order $order) use ($courier): void {
            if ($order->status !== Order::STATUS_READY_FOR_DELIVERY) {
                throw new DomainException('Доставка ещё не готова к назначению');
            }
            if (!$this->deliveryScheduleService->courierClaimWindowIsOpen($order)) {
                throw new DomainException(
                    sprintf(
                        'Заказ станет доступен курьерам за %d часов до интервала доставки',
                        $this->deliveryScheduleService->courierClaimLeadHours()
                    )
                );
            }
            if ($order->courier_id !== null) {
                if ((int) $order->courier_id === (int) $courier->id) {
                    return;
                }

                throw new DomainException('Доставку уже забрал другой курьер');
            }

            $order->update([
                'courier_id' => $courier->id,
                'courier_assigned_at' => now(),
            ]);
        });
    }

    public function release(User $courier, int $orderId, string $reason): Order
    {
        return $this->transition($courier, $orderId, function (Order $order) use ($courier, $reason): void {
            if ($order->status !== Order::STATUS_READY_FOR_DELIVERY) {
                throw new DomainException(
                    'После начала доставки отказаться от неё может только менеджер'
                );
            }
            if ($order->courier_id === null) {
                return;
            }
            $this->assertCourierOwner($courier, $order);
            $entry = sprintf(
                '[%s] Курьер #%d %s вернул доставку в очередь. Причина: %s',
                now()->toIso8601String(),
                $courier->id,
                trim((string) $courier->name),
                trim($reason)
            );
            $order->update([
                'courier_id' => null,
                'courier_assigned_at' => null,
                'internal_notes' => trim(
                    ($order->internal_notes ? $order->internal_notes . "\n" : '')
                    . $entry
                ),
            ]);
        });
    }

    public function start(User $courier, int $orderId): Order
    {
        return $this->transition($courier, $orderId, function (Order $order) use ($courier): void {
            if ($order->status === Order::STATUS_SHIPPED
                && (int) $order->courier_id === (int) $courier->id) {
                return;
            }
            if ($order->status !== Order::STATUS_READY_FOR_DELIVERY) {
                throw new DomainException('Доставка не готова к отправлению');
            }
            $this->assertCourierOwner($courier, $order);
            if ($order->stock_committed_at === null) {
                throw new DomainException(
                    'Склад не подтвердил списание и упаковку заказа'
                );
            }

            $order->update([
                'status' => Order::STATUS_SHIPPED,
                'shipped_at' => now(),
            ]);
            $this->recordStatus(
                $order,
                Order::STATUS_READY_FOR_DELIVERY,
                Order::STATUS_SHIPPED,
                $courier->id,
                $order->isWarehouseTransfer()
                    ? 'Курьер начал межскладское перемещение'
                    : 'Курьер начал клиентскую доставку'
            );
        });
    }

    public function deliver(User $courier, int $orderId): Order
    {
        return $this->transition($courier, $orderId, function (Order $order) use ($courier): void {
            $completedStatus = $order->isWarehouseTransfer()
                ? Order::STATUS_AWAITING_RECEIPT
                : Order::STATUS_DELIVERED;
            if ($order->status === $completedStatus
                && (int) $order->courier_id === (int) $courier->id) {
                return;
            }
            if ($order->status !== Order::STATUS_SHIPPED) {
                throw new DomainException('Сначала необходимо начать доставку');
            }
            $this->assertCourierOwner($courier, $order);

            $updates = ['status' => $completedStatus];
            if ($order->isWarehouseTransfer()) {
                $updates['courier_arrived_at'] = now();
            } else {
                $updates['delivered_at'] = now();
            }
            $order->update($updates);
            $this->recordStatus(
                $order,
                Order::STATUS_SHIPPED,
                $completedStatus,
                $courier->id,
                $order->isWarehouseTransfer()
                    ? 'Курьер прибыл в точку консолидации; ожидается приёмка'
                    : 'Заказ доставлен клиенту'
            );
        });
    }

    public function assignByManager(
        User $manager,
        int $orderId,
        User $courier
    ): Order {
        return DB::transaction(function () use ($manager, $orderId, $courier): Order {
            $order = $this->baseDeliveryQuery()
                ->lockForUpdate()
                ->findOrFail($orderId);
            $this->managerAccessService->assertWarehouseAccess(
                $manager,
                (int) $order->warehouse_id
            );
            if ($order->status !== Order::STATUS_READY_FOR_DELIVERY) {
                throw new DomainException(
                    'Назначать курьера можно только на готовую доставку'
                );
            }
            if (!$this->deliveryScheduleService->courierClaimWindowIsOpen($order)) {
                throw new DomainException(
                    'Окно назначения курьера ещё не открыто'
                );
            }
            if (!$courier->is_active || !$courier->hasRole(User::ROLE_COURIER)) {
                throw new DomainException('Выбранный пользователь не является активным курьером');
            }
            if (!$courier->activeWarehouses()
                ->whereKey($order->warehouse_id)
                ->exists()) {
                throw new DomainException('Курьер не назначен на точку отправления');
            }

            $order->update([
                'courier_id' => $courier->id,
                'courier_assigned_at' => now(),
            ]);

            return $order->fresh($this->relations());
        });
    }

    private function transition(
        User $courier,
        int $orderId,
        callable $callback
    ): Order {
        return DB::transaction(function () use ($courier, $orderId, $callback): Order {
            $order = $this->baseDeliveryQuery()
                ->lockForUpdate()
                ->findOrFail($orderId);
            $this->accessService->assertWarehouse(
                $courier,
                (int) $order->warehouse_id
            );
            $callback($order);

            return $order->fresh($this->relations());
        });
    }

    private function assertCourierOwner(User $courier, Order $order): void
    {
        if ((int) $order->courier_id !== (int) $courier->id
            && !$this->accessService->isAdmin($courier)) {
            throw new DomainException('Доставка назначена другому курьеру');
        }
    }

    private function baseDeliveryQuery(): Builder
    {
        return Order::query()
            ->where('orders.sales_channel', Order::SALES_CHANNEL_ONLINE)
            ->where('orders.is_supplier_order', false)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $transfer): void {
                    $transfer->whereNotNull('orders.parent_order_id')
                        ->whereColumn(
                            'orders.warehouse_id',
                            '!=',
                            'orders.destination_warehouse_id'
                        );
                })->orWhere(function (Builder $customer): void {
                    $customer->whereNull('orders.parent_order_id')
                        ->whereHas('deliveryMethod', fn (Builder $method) =>
                            $method->whereIn('type', [
                                DeliveryMethod::TYPE_COURIER,
                                DeliveryMethod::TYPE_EXPRESS,
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
            'courier',
            'picker',
            'user',
            'shippingAddress',
            'deliveryMethod',
            'parentOrder.user',
            'parentOrder.shippingAddress',
            'parentOrder.deliveryMethod',
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
