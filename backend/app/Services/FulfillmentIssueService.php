<?php

namespace App\Services;

use App\Models\FulfillmentIssue;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FulfillmentIssueService
{
    public function __construct(protected ManagerAccessService $accessService)
    {
    }

    public function issues(User $manager, array $filters): LengthAwarePaginator
    {
        $query = $this->accessibleQuery($manager)
            ->with($this->issueRelations());

        $status = $filters['status'] ?? null;
        if ($status === null) {
            $query->whereIn('status', [
                FulfillmentIssue::STATUS_WAITING,
                FulfillmentIssue::STATUS_IN_REVIEW,
            ]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $query
            ->when($filters['warehouse_id'] ?? null, fn (Builder $builder, int $id) =>
                $builder->where('warehouse_id', $id))
            ->when($filters['product_id'] ?? null, fn (Builder $builder, int $id) =>
                $builder->where('product_id', $id))
            ->when($filters['reason'] ?? null, fn (Builder $builder, string $reason) =>
                $builder->where('reason', $reason))
            ->when(filter_var(
                $filters['mine'] ?? false,
                FILTER_VALIDATE_BOOL
            ), fn (Builder $builder) =>
                $builder->where('manager_id', $manager->id));

        return $query
            ->orderByRaw("CASE status WHEN 'waiting' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    /** @return array{waiting:int, in_review:int, closed:int} */
    public function statusCounts(User $manager): array
    {
        $stored = $this->accessibleQuery($manager)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            FulfillmentIssue::STATUS_WAITING => (int) ($stored[FulfillmentIssue::STATUS_WAITING] ?? 0),
            FulfillmentIssue::STATUS_IN_REVIEW => (int) ($stored[FulfillmentIssue::STATUS_IN_REVIEW] ?? 0),
            FulfillmentIssue::STATUS_CLOSED => (int) ($stored[FulfillmentIssue::STATUS_CLOSED] ?? 0),
        ];
    }

    public function findAccessible(User $manager, int $issueId): FulfillmentIssue
    {
        return $this->accessibleQuery($manager)
            ->with($this->issueRelations())
            ->findOrFail($issueId);
    }

    /**
     * @return array{issue:FulfillmentIssue, orders:LengthAwarePaginator, reserved_quantity_total:int}
     */
    public function affectedOrders(
        User $manager,
        int $issueId,
        int $perPage = 20
    ): array {
        $issue = $this->findAccessible($manager, $issueId);
        $query = $this->affectedOrdersQuery($issue);
        $reservedTotal = (int) (clone $query)->sum('order_products.quantity');

        return [
            'issue' => $issue,
            'orders' => $query->paginate(min(100, max(1, $perPage))),
            'reserved_quantity_total' => $reservedTotal,
        ];
    }

    public function take(User $manager, int $issueId): FulfillmentIssue
    {
        return $this->transition($manager, $issueId, function (FulfillmentIssue $issue) use ($manager): void {
            if ($issue->status === FulfillmentIssue::STATUS_IN_REVIEW) {
                if ((int) $issue->manager_id === (int) $manager->id) {
                    return;
                }

                throw new DomainException('Дело уже рассматривает другой менеджер');
            }
            if ($issue->status === FulfillmentIssue::STATUS_CLOSED) {
                throw new DomainException('Закрытое дело нельзя взять в работу');
            }

            $issue->update([
                'status' => FulfillmentIssue::STATUS_IN_REVIEW,
                'manager_id' => $manager->id,
            ]);
        });
    }

    public function release(User $manager, int $issueId): FulfillmentIssue
    {
        return $this->transition($manager, $issueId, function (FulfillmentIssue $issue) use ($manager): void {
            if ($issue->status === FulfillmentIssue::STATUS_WAITING) {
                return;
            }
            if ($issue->status === FulfillmentIssue::STATUS_CLOSED) {
                throw new DomainException('Закрытое дело нельзя вернуть в очередь');
            }

            $this->assertOwnerOrAdmin($manager, $issue);
            $issue->update([
                'status' => FulfillmentIssue::STATUS_WAITING,
                'manager_id' => null,
            ]);
        });
    }

    public function close(User $manager, int $issueId): FulfillmentIssue
    {
        return $this->transition($manager, $issueId, function (FulfillmentIssue $issue) use ($manager): void {
            if ($issue->status === FulfillmentIssue::STATUS_CLOSED) {
                $this->assertOwnerOrAdmin($manager, $issue);
                return;
            }
            if ($issue->status !== FulfillmentIssue::STATUS_IN_REVIEW) {
                throw new DomainException('Сначала менеджер должен взять дело в работу');
            }

            $this->assertOwnerOrAdmin($manager, $issue);
            $issue->update(['status' => FulfillmentIssue::STATUS_CLOSED]);
        });
    }

    private function transition(User $manager, int $issueId, callable $callback): FulfillmentIssue
    {
        return DB::transaction(function () use ($manager, $issueId, $callback): FulfillmentIssue {
            $issue = FulfillmentIssue::query()->lockForUpdate()->findOrFail($issueId);
            $this->accessService->assertIssueAccess($manager, $issue);
            $callback($issue);

            return $issue->fresh($this->issueRelations());
        });
    }

    private function assertOwnerOrAdmin(User $manager, FulfillmentIssue $issue): void
    {
        if ($this->accessService->isAdmin($manager)) {
            return;
        }

        if ((int) $issue->manager_id !== (int) $manager->id) {
            throw new DomainException('Дело рассматривает другой менеджер');
        }
    }

    private function accessibleQuery(User $manager): Builder
    {
        return $this->accessService->scopeIssues(
            FulfillmentIssue::query(),
            $manager
        );
    }

    private function affectedOrdersQuery(FulfillmentIssue $issue): Builder
    {
        return OrderProduct::query()
            ->select('order_products.*')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->where('order_products.product_id', $issue->product_id)
            ->where('orders.warehouse_id', $issue->warehouse_id)
            ->where('orders.sales_channel', Order::SALES_CHANNEL_ONLINE)
            ->whereNotIn('orders.status', [
                Order::STATUS_CART,
                Order::STATUS_CANCELLED,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('orders.stock_reserved_at')
            ->whereNull('orders.stock_released_at')
            ->whereNull('orders.stock_committed_at')
            ->whereNull('orders.deleted_at')
            ->with([
                'product',
                'order.user',
                'order.shippingAddress',
                'order.deliveryMethod',
                'order.parentOrder.user',
                'order.parentOrder.shippingAddress',
                'order.parentOrder.deliveryMethod',
            ])
            ->orderByDesc('order_products.quantity')
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id');
    }

    /** @return array<int, string|callable> */
    private function issueRelations(): array
    {
        return [
            'product',
            'warehouse',
            'manager',
            'sourceOrder.user',
            'sourceOrder.sellerDevice',
            'sourceOrder.picker',
            'sourceOrder.items.product',
            'sourceOrder.statusHistory.changedBy',
        ];
    }
}
