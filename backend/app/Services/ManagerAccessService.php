<?php

namespace App\Services;

use App\Models\FulfillmentIssue;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ManagerAccessService
{
    public function assertManager(User $user): void
    {
        if (!$user->is_active || !$user->hasAnyRole([
            User::ROLE_ADMIN,
            User::ROLE_MANAGER,
        ])) {
            throw new AuthorizationException(
                'Аккаунт не имеет активной роли менеджера'
            );
        }
    }

    public function isAdmin(User $user): bool
    {
        return $user->hasRole(User::ROLE_ADMIN);
    }

    /** @return Collection<int, int> */
    public function activeWarehouseIds(User $user): Collection
    {
        $this->assertManager($user);

        return $user->activeWarehouses()
            ->pluck('warehouses.id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    public function scopeIssues(Builder $query, User $user): Builder
    {
        $this->assertManager($user);

        if ($this->isAdmin($user)) {
            return $query;
        }

        return $query->whereIn(
            'warehouse_id',
            $this->activeWarehouseIds($user)
        );
    }

    public function scopeOrders(Builder $query, User $user): Builder
    {
        $this->assertManager($user);

        if ($this->isAdmin($user)) {
            return $query;
        }

        $warehouseIds = $this->activeWarehouseIds($user)->all();

        return $query->where(function (Builder $orders) use ($warehouseIds): void {
            $orders
                ->whereIn('warehouse_id', $warehouseIds)
                ->orWhereIn('destination_warehouse_id', $warehouseIds)
                ->orWhereHas('partialOrders', function (Builder $parts) use ($warehouseIds): void {
                    $parts
                        ->whereIn('warehouse_id', $warehouseIds)
                        ->orWhereIn('destination_warehouse_id', $warehouseIds);
                });
        });
    }

    /** @return Collection<int, int> */
    public function involvedWarehouseIds(Order $order): Collection
    {
        $order->loadMissing([
            'partialOrders:id,parent_order_id,warehouse_id,destination_warehouse_id',
        ]);

        return collect([
            $order->warehouse_id,
            $order->destination_warehouse_id,
        ])
            ->concat($order->partialOrders->pluck('warehouse_id'))
            ->concat($order->partialOrders->pluck('destination_warehouse_id'))
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    public function hasAllOrderLocations(User $user, Order $order): bool
    {
        $this->assertManager($user);

        if ($this->isAdmin($user)) {
            return true;
        }

        $required = $this->involvedWarehouseIds($order);
        if ($required->isEmpty()) {
            return false;
        }

        return $required
            ->diff($this->activeWarehouseIds($user))
            ->isEmpty();
    }

    public function assertAllOrderLocations(User $user, Order $order): void
    {
        if (!$this->hasAllOrderLocations($user, $order)) {
            throw new AuthorizationException(
                'Для этой команды менеджер должен быть назначен на все точки заказа'
            );
        }
    }

    public function assertIssueAccess(User $user, FulfillmentIssue $issue): void
    {
        $this->assertManager($user);

        if ($this->isAdmin($user)) {
            return;
        }

        if (!$this->activeWarehouseIds($user)->contains((int) $issue->warehouse_id)) {
            throw new AuthorizationException(
                'Проблема относится к неназначенной или отключённой рабочей точке'
            );
        }
    }

    public function assertWarehouseAccess(User $user, int $warehouseId): void
    {
        $this->assertManager($user);
        if ($this->isAdmin($user)) {
            return;
        }

        if (!$this->activeWarehouseIds($user)->contains($warehouseId)) {
            throw new AuthorizationException(
                'Рабочая точка не назначена менеджеру или отключена'
            );
        }
    }
}
