<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PickerAccessService
{
    public function assertPicker(User $user): void
    {
        if (!$user->is_active || !$user->hasAnyRole([
            User::ROLE_ADMIN,
            User::ROLE_PICKER,
        ])) {
            throw new AuthorizationException(
                'Аккаунт не имеет активной роли сборщика'
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
        $this->assertPicker($user);

        return $user->activeWarehouses()
            ->pluck('warehouses.id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    public function scopeWarehouses(
        Builder $query,
        User $user,
        string $column = 'warehouse_id'
    ): Builder {
        $this->assertPicker($user);
        if ($this->isAdmin($user)) {
            return $query;
        }

        return $query->whereIn($column, $this->activeWarehouseIds($user));
    }

    public function assertWarehouse(User $user, int $warehouseId): void
    {
        $this->assertPicker($user);
        if ($this->isAdmin($user)) {
            return;
        }

        if (!$this->activeWarehouseIds($user)->contains($warehouseId)) {
            throw new AuthorizationException(
                'Заказ относится к неназначенной или отключённой рабочей точке'
            );
        }
    }
}
