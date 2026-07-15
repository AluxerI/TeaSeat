<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\FulfillmentIssue;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StaffDevice;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SellerOrderService
{
    public function __construct(
        protected SellerAccessService $accessService,
        protected SellerPriceSnapshotService $priceSnapshotService,
        protected PricingService $pricingService,
        protected WarehouseService $warehouseService
    ) {
    }

    public function orders(User $seller, array $filters = []): LengthAwarePaginator
    {
        $this->accessService->assertSeller($seller);

        return Order::query()
            ->where('user_id', $seller->id)
            ->where('sales_channel', Order::SALES_CHANNEL_SELLER)
            ->whereNull('parent_order_id')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) =>
                $query->where('status', $status))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, int $warehouseId) =>
                $query->where('warehouse_id', $warehouseId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) =>
                $query->whereDate('seller_occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) =>
                $query->whereDate('seller_occurred_at', '<=', $date))
            ->with(['items.product', 'warehouse', 'sellerDevice', 'fulfillmentIssues'])
            ->orderByDesc('seller_occurred_at')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    public function findOwn(User $seller, int $orderId): Order
    {
        $this->accessService->assertSeller($seller);

        return Order::query()
            ->whereKey($orderId)
            ->where('user_id', $seller->id)
            ->where('sales_channel', Order::SALES_CHANNEL_SELLER)
            ->whereNull('parent_order_id')
            ->with(['items.product', 'warehouse', 'sellerDevice', 'fulfillmentIssues'])
            ->firstOrFail();
    }

    /**
     * Создание и редактирование используют полную версию заказа. Это позволяет
     * одинаково обрабатывать обычный CRUD и накопленную офлайн-синхронизацию.
     *
     * @return array{order:Order, duplicate:bool}
     */
    public function upsert(User $seller, StaffDevice $device, array $payload): array
    {
        $warehouse = $this->accessService->assertAssignedWarehouse(
            $seller,
            (int) $payload['warehouse_id']
        );
        $occurredAt = CarbonImmutable::parse($payload['occurred_at']);
        $normalizedPayload = $this->normalizedPayload($payload, $occurredAt);
        $payloadHash = hash('sha256', json_encode(
            $normalizedPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        return DB::transaction(function () use (
            $seller,
            $device,
            $warehouse,
            $normalizedPayload,
            $occurredAt,
            $payloadHash
        ): array {
            $order = Order::query()
                ->where('user_id', $seller->id)
                ->where('client_order_id', $normalizedPayload['client_order_id'])
                ->where('sales_channel', Order::SALES_CHANNEL_SELLER)
                ->lockForUpdate()
                ->with('items')
                ->first();
            $revision = (int) $normalizedPayload['revision'];

            if ($order && $revision === (int) $order->seller_revision) {
                if (hash_equals((string) $order->last_payload_hash, $payloadHash)) {
                    return [
                        'order' => $order->fresh($this->sellerRelations()),
                        'duplicate' => true,
                    ];
                }

                throw new DomainException(
                    'Та же версия заказа уже получена с другим содержимым'
                );
            }

            if (!$order && $revision !== 1) {
                throw new DomainException('Первая версия локального заказа должна быть равна 1');
            }
            if ($order && $revision !== (int) $order->seller_revision + 1) {
                throw new DomainException(sprintf(
                    'Ожидалась версия %d, получена %d',
                    (int) $order->seller_revision + 1,
                    $revision
                ));
            }

            // Проверка подписи и расчёт выполняются после раннего возврата
            // дубля: уже применённая версия должна оставаться повторяемой,
            // даже если каталог успел измениться после первой синхронизации.
            $quote = $this->priceSnapshotService->quote(
                $normalizedPayload['items'],
                $seller,
                $occurredAt
            );

            $oldQuantities = [];
            $oldStatus = null;
            if ($order) {
                if (!in_array($order->status, [
                    Order::STATUS_PENDING,
                    Order::STATUS_SELLER_REVIEW,
                ], true)) {
                    throw new DomainException('Заказ в текущем статусе нельзя редактировать');
                }
                if ((int) $order->warehouse_id !== (int) $warehouse->id) {
                    throw new DomainException(
                        'Рабочую точку синхронизированного заказа менять нельзя. Отмените и создайте новый заказ'
                    );
                }
                if (!$order->seller_occurred_at?->equalTo($occurredAt)) {
                    throw new DomainException('Время создания синхронизированного заказа менять нельзя');
                }

                $oldQuantities = $order->items
                    ->pluck('quantity', 'product_id')
                    ->map(fn ($quantity) => (int) $quantity)
                    ->all();
                $oldStatus = $order->status;
            } else {
                $order = Order::create([
                    'user_id' => $seller->id,
                    'sales_channel' => Order::SALES_CHANNEL_SELLER,
                    'status' => Order::STATUS_PENDING,
                    'warehouse_id' => $warehouse->id,
                    'client_order_id' => $normalizedPayload['client_order_id'],
                    'seller_occurred_at' => $occurredAt,
                    'paid_at' => $occurredAt,
                    'shipping_cost' => 0,
                ]);
            }

            $newQuantities = collect($normalizedPayload['items'])
                ->pluck('quantity', 'product_id')
                ->map(fn ($quantity) => (int) $quantity)
                ->all();
            $this->warehouseService->syncSellerReservation(
                $order,
                $oldQuantities,
                $newQuantities,
                $seller->id,
                $revision
            );

            $order->items()->delete();
            foreach ($quote['lines'] as $line) {
                $promotion = $line['promotion'];
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'stock_unit' => $line['stock_unit'],
                    'sale_step' => $line['sale_step'],
                    'price_unit_quantity' => $line['price_unit_quantity'],
                    'unit_price' => $line['unit_price'],
                    'promotion_discount_id' => $promotion['id'] ?? null,
                    'selected_discount_id' => null,
                    'promotion_discount_percent' => $promotion
                        && $promotion['value_type'] === Discount::VALUE_PERCENT
                            ? $promotion['value']
                            : 0,
                    'personal_discount_percent' => 0,
                    'promotion_discount_amount' => $line['promotion_discount_amount'],
                    'selected_discount_amount' => 0,
                    'final_unit_price' => $line['final_unit_price'],
                    'total_price' => $line['final_total'],
                    'pricing_snapshot' => $line,
                ]);
            }

            $order->update([
                'status' => Order::STATUS_PENDING,
                'payment_method' => $normalizedPayload['payment_method'],
                'products_total' => $quote['products_total'],
                'promotion_discount' => $quote['promotion_discount'],
                'personal_discount' => 0,
                'cart_discount' => 0,
                'shipping_cost' => 0,
                'shipping_discount' => 0,
                'final_total' => $quote['final_total'],
                'pricing_snapshot' => $quote,
                'seller_revision' => $revision,
                'last_payload_hash' => $payloadHash,
                'seller_device_id' => $device->id,
                'was_edited' => $order->exists && $revision > 1,
                'seller_synced_at' => now(),
                'seller_reviewed_at' => null,
            ]);

            if ($oldStatus === Order::STATUS_SELLER_REVIEW) {
                $this->recordStatus(
                    $order,
                    $oldStatus,
                    Order::STATUS_PENDING,
                    $seller->id,
                    'Продавец исправил заказ и отправил новую версию'
                );
            }

            $this->accessService->markSynced($device, (int) $warehouse->id);

            return [
                'order' => $order->fresh($this->sellerRelations()),
                'duplicate' => false,
            ];
        });
    }

    public function cancel(
        User $seller,
        StaffDevice $device,
        int $orderId,
        int $revision
    ): Order {
        return DB::transaction(function () use ($seller, $device, $orderId, $revision): Order {
            $order = $this->lockOwn($seller, $orderId);
            $this->assertCurrentRevision($order, $revision);

            if ($order->status === Order::STATUS_CANCELLED) {
                return $order->fresh($this->sellerRelations());
            }
            if (!in_array($order->status, [
                Order::STATUS_PENDING,
                Order::STATUS_SELLER_REVIEW,
            ], true)) {
                throw new DomainException('Заказ в текущем статусе нельзя отменить');
            }

            $this->accessService->assertAssignedWarehouse($seller, (int) $order->warehouse_id);
            $this->warehouseService->releaseSellerStockForOrder($order, $seller->id);
            $oldStatus = $order->status;
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'seller_synced_at' => now(),
            ]);
            $this->recordStatus(
                $order,
                $oldStatus,
                Order::STATUS_CANCELLED,
                $seller->id,
                'Офлайн-продажа отменена продавцом до проведения остатка'
            );
            $this->accessService->markSynced($device, (int) $order->warehouse_id);

            return $order->fresh($this->sellerRelations());
        });
    }

    /**
     * @return array{order:Order, result:string, conflicts:array<int, array<string, int|string>>}
     */
    public function complete(
        User $seller,
        StaffDevice $device,
        int $orderId,
        int $revision
    ): array {
        return DB::transaction(function () use ($seller, $device, $orderId, $revision): array {
            $order = $this->lockOwn($seller, $orderId);
            $this->assertCurrentRevision($order, $revision);

            if ($order->status === Order::STATUS_COMPLETED) {
                return [
                    'order' => $order->fresh($this->sellerRelations()),
                    'result' => 'completed',
                    'conflicts' => [],
                ];
            }
            if ($order->status === Order::STATUS_MANAGER_REVIEW) {
                return [
                    'order' => $order->fresh($this->sellerRelations()),
                    'result' => 'manager_review',
                    'conflicts' => [],
                ];
            }
            if (!in_array($order->status, [
                Order::STATUS_PENDING,
                Order::STATUS_SELLER_REVIEW,
            ], true)) {
                throw new DomainException('Заказ в текущем статусе нельзя завершить');
            }

            $this->accessService->assertAssignedWarehouse($seller, (int) $order->warehouse_id);
            $this->lockPromotionUsage($order, $seller);
            $stockResult = $this->warehouseService->completeSellerStockForOrder(
                $order,
                $seller->id,
                false
            );

            if (!$stockResult['committed']) {
                $oldStatus = $order->status;
                $order->update([
                    'status' => Order::STATUS_SELLER_REVIEW,
                    'seller_reviewed_at' => now(),
                    'seller_synced_at' => now(),
                ]);
                if ($oldStatus !== Order::STATUS_SELLER_REVIEW) {
                    $this->recordStatus(
                        $order,
                        $oldStatus,
                        Order::STATUS_SELLER_REVIEW,
                        $seller->id,
                        'Завершение отклонено: продавцу нужно проверить расхождение остатков'
                    );
                }
                $this->accessService->markSynced($device, (int) $order->warehouse_id);

                return [
                    'order' => $order->fresh($this->sellerRelations()),
                    'result' => 'seller_review',
                    'conflicts' => $stockResult['conflicts'],
                ];
            }

            $this->completeOrderRecord($order, $seller, $device);

            return [
                'order' => $order->fresh($this->sellerRelations()),
                'result' => 'completed',
                'conflicts' => [],
            ];
        });
    }

    /**
     * @return array{order:Order, result:string, conflicts:array<int, array<string, int|string>>}
     */
    public function escalate(
        User $seller,
        StaffDevice $device,
        int $orderId,
        int $revision
    ): array {
        return DB::transaction(function () use ($seller, $device, $orderId, $revision): array {
            $order = $this->lockOwn($seller, $orderId);
            $this->assertCurrentRevision($order, $revision);

            if ($order->status === Order::STATUS_MANAGER_REVIEW) {
                return [
                    'order' => $order->fresh($this->sellerRelations()),
                    'result' => 'manager_review',
                    'conflicts' => [],
                ];
            }
            if ($order->status === Order::STATUS_COMPLETED) {
                return [
                    'order' => $order->fresh($this->sellerRelations()),
                    'result' => 'completed',
                    'conflicts' => [],
                ];
            }
            if ($order->status !== Order::STATUS_SELLER_REVIEW) {
                throw new DomainException(
                    'Передать менеджеру можно только заказ после отклонённого завершения'
                );
            }

            $this->accessService->assertAssignedWarehouse($seller, (int) $order->warehouse_id);
            $this->lockPromotionUsage($order, $seller);
            $stockResult = $this->warehouseService->completeSellerStockForOrder(
                $order,
                $seller->id,
                true
            );

            if ($stockResult['conflicts'] === []) {
                $this->completeOrderRecord($order, $seller, $device);

                return [
                    'order' => $order->fresh($this->sellerRelations()),
                    'result' => 'completed',
                    'conflicts' => [],
                ];
            }

            foreach ($stockResult['conflicts'] as $conflict) {
                FulfillmentIssue::query()->firstOrCreate([
                    'source_order_id' => $order->id,
                    'product_id' => $conflict['product_id'],
                    'reason' => $conflict['reason'],
                ], [
                    'warehouse_id' => $conflict['warehouse_id'],
                    'shortage_quantity' => $conflict['shortage_quantity'],
                    'reserved_online_before' => $conflict['reserved_online_before'],
                    'reserved_seller_before' => $conflict['reserved_seller_before'],
                    'status' => FulfillmentIssue::STATUS_WAITING,
                ]);
            }

            $oldStatus = $order->status;
            $this->consumePromotionUsage($order, $seller);
            $order->update([
                'status' => Order::STATUS_MANAGER_REVIEW,
                'seller_escalated_at' => now(),
                'seller_synced_at' => now(),
            ]);
            $this->recordStatus(
                $order,
                $oldStatus,
                Order::STATUS_MANAGER_REVIEW,
                $seller->id,
                'Продавец подтвердил расхождение и передал дело менеджеру'
            );
            $this->accessService->markSynced($device, (int) $order->warehouse_id);

            return [
                'order' => $order->fresh($this->sellerRelations()),
                'result' => 'manager_review',
                'conflicts' => $stockResult['conflicts'],
            ];
        });
    }

    private function completeOrderRecord(
        Order $order,
        User $seller,
        StaffDevice $device
    ): void {
        $oldStatus = $order->status;
        $this->consumePromotionUsage($order, $seller);
        $order->update([
            'status' => Order::STATUS_COMPLETED,
            'seller_completed_at' => now(),
            'seller_synced_at' => now(),
        ]);
        $this->recordStatus(
            $order,
            $oldStatus,
            Order::STATUS_COMPLETED,
            $seller->id,
            'Продажа завершена продавцом, физический остаток проведён'
        );
        $this->accessService->markSynced($device, (int) $order->warehouse_id);
    }

    private function consumePromotionUsage(Order $order, User $seller): void
    {
        $this->pricingService->consumeSellerOfflineUsage(
            $seller,
            $order,
            is_array($order->pricing_snapshot) ? $order->pricing_snapshot : []
        );
    }

    private function lockPromotionUsage(Order $order, User $seller): void
    {
        $this->pricingService->lockSellerOfflineUsage(
            $seller,
            is_array($order->pricing_snapshot) ? $order->pricing_snapshot : []
        );
    }

    private function lockOwn(User $seller, int $orderId): Order
    {
        return Order::query()
            ->whereKey($orderId)
            ->where('user_id', $seller->id)
            ->where('sales_channel', Order::SALES_CHANNEL_SELLER)
            ->whereNull('parent_order_id')
            ->lockForUpdate()
            ->with('items')
            ->firstOrFail();
    }

    private function assertCurrentRevision(Order $order, int $revision): void
    {
        if ((int) $order->seller_revision !== $revision) {
            throw new DomainException(sprintf(
                'Команда относится к версии %d, актуальная версия заказа — %d',
                $revision,
                (int) $order->seller_revision
            ));
        }
    }

    private function recordStatus(
        Order $order,
        string $from,
        string $to,
        int $actorId,
        string $notes
    ): void {
        if ($from === $to) {
            return;
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $actorId,
            'notes' => $notes,
        ]);
    }

    private function normalizedPayload(array $payload, CarbonImmutable $occurredAt): array
    {
        $items = collect($payload['items'])
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'pricing_token' => (string) $item['pricing_token'],
            ])
            ->sortBy('product_id')
            ->values()
            ->all();

        return [
            'client_order_id' => mb_strtolower((string) $payload['client_order_id']),
            'revision' => (int) $payload['revision'],
            'warehouse_id' => (int) $payload['warehouse_id'],
            'occurred_at' => $occurredAt->toIso8601String(),
            'payment_method' => (string) $payload['payment_method'],
            'items' => $items,
        ];
    }

    /** @return array<int, string> */
    private function sellerRelations(): array
    {
        return [
            'items.product',
            'warehouse',
            'sellerDevice',
            'fulfillmentIssues',
        ];
    }
}
