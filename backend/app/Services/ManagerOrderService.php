<?php

namespace App\Services;

use App\Models\FulfillmentIssue;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class ManagerOrderService
{
    public function __construct(
        protected ManagerAccessService $accessService
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function orders(User $manager, array $filters): LengthAwarePaginator
    {
        $query = $this->rootOrdersQuery();
        $this->accessService->scopeOrders($query, $manager);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['sales_channel'])) {
            $query->where('sales_channel', $filters['sales_channel']);
        }
        if (!empty($filters['warehouse_id'])) {
            $warehouseId = (int) $filters['warehouse_id'];
            $this->accessService->assertWarehouseAccess($manager, $warehouseId);
            $this->whereTouchesWarehouse($query, $warehouseId);
        }
        if (array_key_exists('has_issue', $filters)) {
            $this->whereHasOpenIssue($query, (bool) $filters['has_issue']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $this->applySearch($query, trim((string) $filters['search']));
        }

        return $query
            ->with($this->listRelations())
            ->latest('orders.created_at')
            ->latest('orders.id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function findAccessible(User $manager, int $orderId): Order
    {
        $query = $this->rootOrdersQuery();
        $this->accessService->scopeOrders($query, $manager);

        /** @var Order|null $order */
        $order = $query
            ->with($this->detailRelations())
            ->whereKey($orderId)
            ->first();

        if (!$order) {
            throw (new ModelNotFoundException())->setModel(Order::class, [$orderId]);
        }

        return $order;
    }

    /** @return array<int, int> */
    public function activeWarehouseIds(User $manager): array
    {
        if ($this->accessService->isAdmin($manager)) {
            return [];
        }

        return $this->accessService
            ->activeWarehouseIds($manager)
            ->all();
    }

    private function rootOrdersQuery(): Builder
    {
        return Order::query()
            ->realOrders()
            ->whereNull('parent_order_id');
    }

    private function whereTouchesWarehouse(Builder $query, int $warehouseId): void
    {
        $query->where(function (Builder $orders) use ($warehouseId): void {
            $orders
                ->where('warehouse_id', $warehouseId)
                ->orWhere('destination_warehouse_id', $warehouseId)
                ->orWhereHas('partialOrders', function (Builder $parts) use ($warehouseId): void {
                    $parts
                        ->where('warehouse_id', $warehouseId)
                        ->orWhere('destination_warehouse_id', $warehouseId);
                });
        });
    }

    private function whereHasOpenIssue(Builder $query, bool $hasIssue): void
    {
        $callback = function (Builder $orders): void {
            $orders
                ->whereHas('fulfillmentIssues', fn (Builder $issues) =>
                    $issues->where('status', '!=', FulfillmentIssue::STATUS_CLOSED))
                ->orWhereHas('partialOrders.fulfillmentIssues', fn (Builder $issues) =>
                    $issues->where('status', '!=', FulfillmentIssue::STATUS_CLOSED));
        };

        if ($hasIssue) {
            $query->where($callback);

            return;
        }

        $query->whereNot($callback);
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $pattern = '%' . mb_strtolower($search) . '%';
        $numericId = preg_match('/^(?:TE-)?0*(\d+)$/i', $search, $matches)
            ? (int) $matches[1]
            : null;

        $query->where(function (Builder $orders) use ($pattern, $numericId): void {
            if ($numericId !== null) {
                $orders->whereKey($numericId);
            }

            $method = $numericId === null ? 'whereRaw' : 'orWhereRaw';
            $orders->{$method}(
                'LOWER(COALESCE(contact_name, \'\')) LIKE ?',
                [$pattern]
            )
                ->orWhereRaw(
                    'LOWER(COALESCE(contact_email, \'\')) LIKE ?',
                    [$pattern]
                )
                ->orWhere('contact_phone', 'LIKE', $pattern)
                ->orWhereHas('user', function (Builder $users) use ($pattern): void {
                    $users
                        ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$pattern])
                        ->orWhere('phone', 'LIKE', $pattern);
                });
        });
    }

    /** @return array<int, string> */
    private function listRelations(): array
    {
        return [
            'user',
            'warehouse',
            'destinationWarehouse',
            'deliveryMethod',
            'picker',
            'courier',
            'fulfillmentIssues:id,source_order_id,status',
            'partialOrders:id,parent_order_id,warehouse_id,destination_warehouse_id,status,picker_id,courier_id,final_total,stock_reserved_at,stock_committed_at,stock_released_at,internal_notes',
            'partialOrders.warehouse',
            'partialOrders.destinationWarehouse',
            'partialOrders.picker',
            'partialOrders.courier',
            'partialOrders.fulfillmentIssues:id,source_order_id,status',
        ];
    }

    /** @return array<int, string> */
    private function detailRelations(): array
    {
        return [
            'user',
            'deliveryMethod',
            'deliveryTimeSlot',
            'shippingAddress',
            'warehouse',
            'destinationWarehouse',
            'picker',
            'courier',
            'sellerDevice',
            'discount',
            'items.product',
            'items.productSize',
            'gifts.items.product',
            'gifts.items.productSize',
            'fulfillmentIssues.product',
            'fulfillmentIssues.warehouse',
            'fulfillmentIssues.manager',
            'inventoryMovements.product',
            'inventoryMovements.warehouse',
            'inventoryMovements.actor',
            'statusHistory.changedBy',
            'managerAdjustments.manager',
            'managerAdjustments.fulfillmentIssue',
            'supplierOrder.supplier',
            'partialOrders.user',
            'partialOrders.deliveryMethod',
            'partialOrders.shippingAddress',
            'partialOrders.warehouse',
            'partialOrders.destinationWarehouse',
            'partialOrders.picker',
            'partialOrders.courier',
            'partialOrders.items.product',
            'partialOrders.items.productSize',
            'partialOrders.gifts.items.product',
            'partialOrders.gifts.items.productSize',
            'partialOrders.fulfillmentIssues.product',
            'partialOrders.fulfillmentIssues.warehouse',
            'partialOrders.fulfillmentIssues.manager',
            'partialOrders.inventoryMovements.product',
            'partialOrders.inventoryMovements.warehouse',
            'partialOrders.inventoryMovements.actor',
            'partialOrders.statusHistory.changedBy',
        ];
    }
}
