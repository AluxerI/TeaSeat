<?php

namespace App\Services;

use App\Models\FulfillmentIssue;
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
