<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderRequestService
{
    public function __construct(protected ManagerAccessService $managerAccess)
    {
    }

    public function customerRequests(User $user, int $orderId): Collection
    {
        $order = $this->customerOrder($user, $orderId);

        return $order->requests()
            ->with('manager:id,name')
            ->get();
    }

    public function create(
        User $user,
        int $orderId,
        string $type,
        ?string $message
    ): OrderRequest {
        return DB::transaction(function () use ($user, $orderId, $type, $message): OrderRequest {
            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereNull('parent_order_id')
                ->lockForUpdate()
                ->findOrFail($orderId);

            $this->assertCustomerRequestAllowed($order, $type);
            if ($order->requests()
                ->where('type', $type)
                ->whereIn('status', OrderRequest::OPEN_STATUSES)
                ->exists()) {
                throw new DomainException('Такое обращение по заказу уже ожидает обработки');
            }

            return $order->requests()->create([
                'user_id' => $user->id,
                'type' => $type,
                'message' => $message,
                'status' => OrderRequest::STATUS_WAITING,
            ])->fresh(['manager:id,name']);
        }, 3);
    }

    public function withdraw(User $user, int $requestId): OrderRequest
    {
        return DB::transaction(function () use ($user, $requestId): OrderRequest {
            $request = OrderRequest::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($requestId);
            if ($request->status === OrderRequest::STATUS_WITHDRAWN) {
                return $request->fresh(['manager:id,name']);
            }
            if ($request->status !== OrderRequest::STATUS_WAITING) {
                throw new DomainException('Можно отозвать только ожидающее обращение');
            }

            $request->update([
                'status' => OrderRequest::STATUS_WITHDRAWN,
                'withdrawn_at' => now(),
            ]);

            return $request->fresh(['manager:id,name']);
        }, 3);
    }

    public function managerRequests(User $manager, array $filters): LengthAwarePaginator
    {
        $query = $this->managerQuery($manager)->with($this->managerRelations());
        $status = $filters['status'] ?? null;
        if ($status === null) {
            $query->whereIn('status', OrderRequest::OPEN_STATUSES);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $query
            ->when($filters['type'] ?? null, fn (Builder $builder, string $type) =>
                $builder->where('type', $type))
            ->when($filters['order_id'] ?? null, fn (Builder $builder, int $orderId) =>
                $builder->where('order_id', $orderId))
            ->when(filter_var($filters['mine'] ?? false, FILTER_VALIDATE_BOOL),
                fn (Builder $builder) => $builder->where('manager_id', $manager->id));

        return $query
            ->orderByRaw("CASE status WHEN 'waiting' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))));
    }

    public function managerShow(User $manager, int $requestId): OrderRequest
    {
        return $this->managerQuery($manager)
            ->with($this->managerRelations())
            ->findOrFail($requestId);
    }

    public function statusCounts(User $manager): array
    {
        $stored = $this->managerQuery($manager)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect([
            OrderRequest::STATUS_WAITING,
            OrderRequest::STATUS_IN_REVIEW,
            OrderRequest::STATUS_RESOLVED,
            OrderRequest::STATUS_REJECTED,
            OrderRequest::STATUS_WITHDRAWN,
        ])->mapWithKeys(fn (string $status): array => [
            $status => (int) ($stored[$status] ?? 0),
        ])->all();
    }

    public function take(User $manager, int $requestId): OrderRequest
    {
        return $this->transition($manager, $requestId, function (OrderRequest $request) use ($manager): void {
            if ($request->status === OrderRequest::STATUS_IN_REVIEW) {
                if ((int) $request->manager_id === (int) $manager->id) {
                    return;
                }
                throw new DomainException('Обращение уже рассматривает другой менеджер');
            }
            if ($request->status !== OrderRequest::STATUS_WAITING) {
                throw new DomainException('Закрытое обращение нельзя взять в работу');
            }

            $request->update([
                'status' => OrderRequest::STATUS_IN_REVIEW,
                'manager_id' => $manager->id,
                'taken_at' => now(),
            ]);
        });
    }

    public function release(User $manager, int $requestId): OrderRequest
    {
        return $this->transition($manager, $requestId, function (OrderRequest $request) use ($manager): void {
            if ($request->status === OrderRequest::STATUS_WAITING) {
                return;
            }
            if ($request->status !== OrderRequest::STATUS_IN_REVIEW) {
                throw new DomainException('Закрытое обращение нельзя вернуть в очередь');
            }
            $this->assertOwnerOrAdmin($manager, $request);
            $request->update([
                'status' => OrderRequest::STATUS_WAITING,
                'manager_id' => null,
                'taken_at' => null,
            ]);
        });
    }

    public function resolve(User $manager, int $requestId, string $comment): OrderRequest
    {
        return $this->finish(
            $manager,
            $requestId,
            OrderRequest::STATUS_RESOLVED,
            $comment
        );
    }

    public function reject(User $manager, int $requestId, string $comment): OrderRequest
    {
        return $this->finish(
            $manager,
            $requestId,
            OrderRequest::STATUS_REJECTED,
            $comment
        );
    }

    private function finish(
        User $manager,
        int $requestId,
        string $status,
        string $comment
    ): OrderRequest {
        return $this->transition($manager, $requestId, function (OrderRequest $request) use (
            $manager,
            $status,
            $comment
        ): void {
            if ($request->status === $status) {
                $this->assertOwnerOrAdmin($manager, $request);
                return;
            }
            if ($request->status !== OrderRequest::STATUS_IN_REVIEW) {
                throw new DomainException('Сначала менеджер должен взять обращение в работу');
            }
            $this->assertOwnerOrAdmin($manager, $request);
            $request->update([
                'status' => $status,
                'manager_comment' => $comment,
                'resolved_at' => now(),
            ]);
        });
    }

    private function transition(User $manager, int $requestId, callable $callback): OrderRequest
    {
        return DB::transaction(function () use ($manager, $requestId, $callback): OrderRequest {
            $request = $this->managerQuery($manager)
                ->lockForUpdate()
                ->findOrFail($requestId);
            $callback($request);

            return $request->fresh($this->managerRelations());
        }, 3);
    }

    private function managerQuery(User $manager): Builder
    {
        $this->managerAccess->assertManager($manager);

        return OrderRequest::query()->whereHas(
            'order',
            fn (Builder $orders): Builder => $this->managerAccess->scopeOrders($orders, $manager)
        );
    }

    private function customerOrder(User $user, int $orderId): Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereNull('parent_order_id')
            ->findOrFail($orderId);
    }

    private function assertCustomerRequestAllowed(Order $order, string $type): void
    {
        if ($order->sales_channel !== Order::SALES_CHANNEL_ONLINE
            || $order->status === Order::STATUS_CART) {
            throw new DomainException('Обращение можно создать только по оформленному интернет-заказу');
        }
        if (in_array($order->status, [
            Order::STATUS_CANCELLED,
            Order::STATUS_COMPLETED,
        ], true)) {
            throw new DomainException('По закрытому заказу нельзя создать новое обращение');
        }
        if ($order->status === Order::STATUS_DELIVERED
            && !in_array($type, [
                OrderRequest::TYPE_ORDER_PROBLEM,
                OrderRequest::TYPE_OTHER,
            ], true)) {
            throw new DomainException(
                'После доставки доступны только сообщения о проблеме и другие обращения'
            );
        }
    }

    private function assertOwnerOrAdmin(User $manager, OrderRequest $request): void
    {
        if ($this->managerAccess->isAdmin($manager)) {
            return;
        }
        if ((int) $request->manager_id !== (int) $manager->id) {
            throw new DomainException('Обращение рассматривает другой менеджер');
        }
    }

    private function managerRelations(): array
    {
        return [
            'manager:id,name',
            'user:id,name,email,phone',
            'order:id,user_id,status,warehouse_id,destination_warehouse_id,created_at',
            'order.warehouse:id,name',
            'order.destinationWarehouse:id,name',
        ];
    }
}
