<?php

namespace App\Services;

use App\Models\StaffDevice;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

class SellerAccessService
{
    public function assertSeller(User $user): void
    {
        if (!$user->is_active || !$user->hasRole(User::ROLE_SELLER)) {
            throw new DomainException('Аккаунт не имеет активной роли продавца');
        }
    }

    public function registerDevice(
        User $user,
        string $deviceUuid,
        string $name,
        ?int $warehouseId = null,
        ?int $accessTokenId = null
    ): StaffDevice {
        $this->assertSeller($user);
        // Sanctum::actingAs() использует mock-токен с ключом 0. Это не запись
        // personal_access_tokens, поэтому такой идентификатор хранить нельзя.
        $accessTokenId = $accessTokenId !== null && $accessTokenId > 0
            ? $accessTokenId
            : null;
        $warehouse = $warehouseId
            ? $this->assertAssignedWarehouse($user, $warehouseId)
            : null;

        return DB::transaction(function () use (
            $user,
            $deviceUuid,
            $name,
            $warehouse,
            $accessTokenId
        ): StaffDevice {
            $device = StaffDevice::query()
                ->where('user_id', $user->id)
                ->where('device_uuid', mb_strtolower($deviceUuid))
                ->lockForUpdate()
                ->first();

            if ($device?->isRevoked()) {
                throw new DomainException('Это устройство отозвано администратором');
            }

            if (!$device) {
                $device = new StaffDevice([
                    'user_id' => $user->id,
                    'device_uuid' => mb_strtolower($deviceUuid),
                ]);
            }

            $device->fill([
                'name' => trim($name),
                'last_warehouse_id' => $warehouse?->id,
                'personal_access_token_id' => $accessTokenId,
                'last_seen_at' => now(),
            ])->save();

            return $device->fresh('lastWarehouse');
        });
    }

    public function resolveDevice(
        User $user,
        string $deviceUuid,
        ?int $warehouseId = null
    ): StaffDevice {
        $this->assertSeller($user);
        $device = StaffDevice::query()
            ->where('user_id', $user->id)
            ->where('device_uuid', mb_strtolower($deviceUuid))
            ->first();

        if (!$device || $device->isRevoked()) {
            throw new DomainException('Устройство не зарегистрировано или отозвано');
        }

        $updates = ['last_seen_at' => now()];
        if ($warehouseId !== null) {
            $warehouse = $this->assertAssignedWarehouse($user, $warehouseId);
            $updates['last_warehouse_id'] = $warehouse->id;
        }
        $device->update($updates);

        return $device->fresh('lastWarehouse');
    }

    public function assertAssignedWarehouse(User $user, int $warehouseId): Warehouse
    {
        $this->assertSeller($user);

        $warehouse = $user->activeWarehouses()
            ->whereKey($warehouseId)
            ->first();

        if (!$warehouse) {
            throw new DomainException(
                'Рабочая точка не назначена продавцу, отключена или больше не активна'
            );
        }

        return $warehouse;
    }

    public function markSynced(StaffDevice $device, ?int $warehouseId = null): void
    {
        $device->update([
            'last_warehouse_id' => $warehouseId ?? $device->last_warehouse_id,
            'last_seen_at' => now(),
            'last_sync_at' => now(),
        ]);
    }
}
