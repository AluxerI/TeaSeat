<?php

namespace App\Services;

use App\Models\FulfillmentIssue;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ManagerOrderCommandService
{
    public function __construct(
        protected ManagerAccessService $accessService,
        protected ManagerOrderService $orderService,
        protected OrderManagementService $managementService
    ) {
    }

    public function addInternalNote(
        User $manager,
        int $orderId,
        string $comment
    ): Order {
        DB::transaction(function () use ($manager, $orderId, $comment): void {
            $order = $this->lockAccessibleRoot($manager, $orderId);
            $timestamp = now()->toIso8601String();
            $author = trim((string) $manager->name);
            $entry = "[{$timestamp}] Менеджер #{$manager->id} {$author}: "
                . trim($comment);

            $order->update([
                'internal_notes' => trim(
                    ($order->internal_notes ? $order->internal_notes . "\n" : '')
                    . $entry
                ),
            ]);
        });

        return $this->orderService->findAccessible($manager, $orderId);
    }

    public function confirm(User $manager, int $orderId): Order
    {
        DB::transaction(function () use ($manager, $orderId): void {
            $order = $this->lockAccessibleRoot($manager, $orderId);
            $this->accessService->assertAllOrderLocations($manager, $order);
            $this->assertOnlineOrder($order);

            if ($order->status === Order::STATUS_CONFIRMED) {
                return;
            }
            if ($order->status !== Order::STATUS_PENDING) {
                throw new DomainException(
                    'Подтвердить можно только заказ в статусе ожидания'
                );
            }
            if ($this->hasOpenIssues($order)) {
                throw new DomainException(
                    'Сначала необходимо закрыть все проблемы исполнения заказа'
                );
            }

            $this->managementService->confirmOrder($order, $manager->id);
        });

        return $this->orderService->findAccessible($manager, $orderId);
    }

    public function cancel(
        User $manager,
        int $orderId,
        string $reason
    ): Order {
        DB::transaction(function () use ($manager, $orderId, $reason): void {
            $order = $this->lockAccessibleRoot($manager, $orderId);
            $this->accessService->assertAllOrderLocations($manager, $order);
            $this->assertOnlineOrder($order);

            if ($order->status === Order::STATUS_CANCELLED) {
                return;
            }
            if ($order->paid_at !== null) {
                throw new DomainException(
                    'Оплаченный заказ нельзя отменить до оформления возврата оплаты'
                );
            }

            $this->managementService->cancelOrderByManager(
                $order,
                trim($reason),
                $manager->id
            );
        });

        return $this->orderService->findAccessible($manager, $orderId);
    }

    /** @return array<int, int> */
    public function activeWarehouseIds(User $manager): array
    {
        return $this->orderService->activeWarehouseIds($manager);
    }

    private function lockAccessibleRoot(User $manager, int $orderId): Order
    {
        $query = Order::query()
            ->realOrders()
            ->whereNull('parent_order_id');
        $this->accessService->scopeOrders($query, $manager);

        /** @var Order|null $order */
        $order = $query->whereKey($orderId)->lockForUpdate()->first();
        if (!$order) {
            throw (new ModelNotFoundException())->setModel(
                Order::class,
                [$orderId]
            );
        }

        $parts = Order::query()
            ->where('parent_order_id', $order->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $order->setRelation('partialOrders', $parts);

        return $order;
    }

    private function hasOpenIssues(Order $order): bool
    {
        $orderIds = collect([$order->id])
            ->concat($order->partialOrders->pluck('id'))
            ->map(fn ($id): int => (int) $id)
            ->all();

        return FulfillmentIssue::query()
            ->whereIn('source_order_id', $orderIds)
            ->where('status', '!=', FulfillmentIssue::STATUS_CLOSED)
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']) !== null;
    }

    private function assertOnlineOrder(Order $order): void
    {
        if ($order->sales_channel !== Order::SALES_CHANNEL_ONLINE
            || $order->is_supplier_order) {
            throw new DomainException(
                'Эта команда доступна только для интернет-заказов покупателей'
            );
        }
    }
}
