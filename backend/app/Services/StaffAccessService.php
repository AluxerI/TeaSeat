<?php

namespace App\Services;

use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

class StaffAccessService
{
    /**
     * @param  array<int, int|string>  $warehouseIds
     */
    public function syncActiveLocations(User $user, array $warehouseIds): User
    {
        $warehouseIds = collect($warehouseIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($warehouseIds->isNotEmpty() && !$user->isStaff()) {
            throw new DomainException('Рабочие точки можно назначать только сотрудникам.');
        }

        $validWarehouseIds = Warehouse::query()
            ->active()
            ->whereKey($warehouseIds)
            ->pluck('id');

        if ($validWarehouseIds->count() !== $warehouseIds->count()) {
            throw new DomainException('Одна из выбранных рабочих точек не существует или отключена.');
        }

        DB::transaction(function () use ($user, $warehouseIds): void {
            $now = now();

            User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            DB::table('user_warehouse')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);

            foreach ($warehouseIds as $warehouseId) {
                $existing = DB::table('user_warehouse')
                    ->where('user_id', $user->id)
                    ->where('warehouse_id', $warehouseId)
                    ->exists();

                if ($existing) {
                    DB::table('user_warehouse')
                        ->where('user_id', $user->id)
                        ->where('warehouse_id', $warehouseId)
                        ->update([
                            'is_active' => true,
                            'updated_at' => $now,
                        ]);

                    continue;
                }

                DB::table('user_warehouse')->insert([
                    'user_id' => $user->id,
                    'warehouse_id' => $warehouseId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $user->clearCache();

        return $user->refresh()->load(['roles', 'activeWarehouses']);
    }

    /**
     * @return array<int, int>
     */
    public function activeLocationIds(User $user): array
    {
        return $user->activeWarehouses()
            ->pluck('warehouses.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
