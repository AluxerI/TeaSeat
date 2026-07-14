<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CourierAccessService
{
    public function assertCourier(User $user): void
    {
        if (!$user->is_active || !$user->hasAnyRole([
            User::ROLE_ADMIN,
            User::ROLE_COURIER,
        ])) {
            throw new AuthorizationException(
                'Аккаунт не имеет активной роли курьера'
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
        $this->assertCourier($user);

        return $user->activeWarehouses()
            ->pluck('warehouses.id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    public function scopeDeliveries(Builder $query, User $user): Builder
    {
        $this->assertCourier($user);
        if ($this->isAdmin($user)) {
            return $query;
        }

        return $query->whereIn(
            'orders.warehouse_id',
            $this->activeWarehouseIds($user)
        );
    }

    public function assertWarehouse(User $user, int $warehouseId): void
    {
        $this->assertCourier($user);
        if ($this->isAdmin($user)) {
            return;
        }

        if (!$this->activeWarehouseIds($user)->contains($warehouseId)) {
            throw new AuthorizationException(
                'Доставка относится к неназначенной или отключённой точке'
            );
        }
    }
}
