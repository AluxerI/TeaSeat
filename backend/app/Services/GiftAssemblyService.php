<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GiftAssemblyService
{
    public function __construct(protected PickerAccessService $accessService)
    {
    }

    public function catalog(User $picker, ?int $warehouseId, int $perPage): LengthAwarePaginator
    {
        $this->accessService->assertPicker($picker);
        if ($warehouseId !== null) {
            $this->accessService->assertWarehouse($picker, $warehouseId);
        }

        return Product::query()
            ->where('product_type', Product::TYPE_PREASSEMBLED_GIFT)
            ->with(['inventories' => function ($query) use ($picker, $warehouseId): void {
                $query->when($warehouseId, fn ($inventory) => $inventory->where('warehouse_id', $warehouseId));
                if (!$this->accessService->isAdmin($picker)) {
                    $query->whereIn('warehouse_id', $this->accessService->activeWarehouseIds($picker));
                }
                $query->with('warehouse');
            }])
            ->orderBy('name')
            ->paginate(min(100, max(1, $perPage)));
    }

    public function replenish(
        User $picker,
        Product $finishedGift,
        int $warehouseId,
        int $producedQuantity,
        array $consumedItems,
        string $idempotencyKey,
        ?string $comment
    ): array {
        $this->accessService->assertWarehouse($picker, $warehouseId);
        if (!$finishedGift->isPreassembledGift()) {
            throw new DomainException('Пополнять этим маршрутом можно только готовые подарочные SKU');
        }

        $consumption = collect($consumedItems)
            ->groupBy('product_id')
            ->map(fn ($items): int => (int) $items->sum('quantity'));
        if ($consumption->has((int) $finishedGift->id)) {
            throw new DomainException('Готовый подарок не может быть собственным компонентом');
        }
        $finishedGift->assertValidSaleQuantity($producedQuantity);
        $componentProducts = Product::query()
            ->whereIn('id', $consumption->keys())
            ->get()
            ->keyBy('id');
        foreach ($consumption as $productId => $quantity) {
            $componentProducts->get((int) $productId)?->assertValidSaleQuantity($quantity);
        }

        $movementPrefix = "gift_assembly:{$idempotencyKey}";
        $payloadHash = hash('sha256', json_encode([
            'warehouse_id' => $warehouseId,
            'finished_product_id' => (int) $finishedGift->id,
            'produced_quantity' => $producedQuantity,
            'consumed_items' => $consumption->sortKeys()->all(),
        ], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use (
            $picker,
            $finishedGift,
            $warehouseId,
            $producedQuantity,
            $consumption,
            $movementPrefix,
            $comment,
            $idempotencyKey,
            $payloadHash
        ): array {
            // UUID защищает от повтора запроса, advisory lock — от двух
            // одновременных транзакций с тем же UUID в PostgreSQL.
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$movementPrefix]);
            $existing = InventoryMovement::query()
                ->where('idempotency_key', "{$movementPrefix}:produce")
                ->first();
            if ($existing) {
                if (($existing->metadata['payload_hash'] ?? null) !== $payloadHash) {
                    throw new DomainException('idempotency_key уже использован с другим составом сборки');
                }
                return [
                    'already_applied' => true,
                    'batch_id' => $idempotencyKey,
                    'finished_product_id' => (int) $finishedGift->id,
                    'warehouse_id' => $warehouseId,
                    'produced_quantity' => (int) $existing->physical_delta,
                ];
            }

            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [
                "gift_finished:{$warehouseId}:{$finishedGift->id}",
            ]);
            $warehouse = Warehouse::query()->active()->findOrFail($warehouseId);
            Inventory::query()->firstOrCreate([
                'warehouse_id' => $warehouse->id,
                'product_id' => $finishedGift->id,
            ], [
                'quantity' => 0,
                'reserved_online_quantity' => 0,
                'reserved_seller_quantity' => 0,
            ]);

            $productIds = $consumption->keys()
                ->push((int) $finishedGift->id)
                ->unique()
                ->sort()
                ->values();
            $inventories = Inventory::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $productIds)
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($consumption as $productId => $quantity) {
                /** @var Inventory|null $inventory */
                $inventory = $inventories->get((int) $productId);
                if (!$inventory) {
                    throw new DomainException("Компонент #{$productId} отсутствует на выбранном складе");
                }
                if ($inventory->availableQuantity() < $quantity) {
                    throw new DomainException(sprintf(
                        'Недостаточно свободного компонента #%d. Требуется: %d, доступно: %d',
                        $productId,
                        $quantity,
                        $inventory->availableQuantity()
                    ));
                }
            }

            foreach ($consumption as $productId => $quantity) {
                /** @var Inventory $inventory */
                $inventory = $inventories->get((int) $productId);
                $before = $this->balances($inventory);
                $inventory->quantity -= $quantity;
                $inventory->save();
                $this->movement(
                    $inventory,
                    InventoryMovement::TYPE_GIFT_ASSEMBLY_CONSUME,
                    $before,
                    -$quantity,
                    $picker->id,
                    $comment ?: 'Компонент использован для сборки готового подарка',
                    "{$movementPrefix}:consume:{$productId}",
                    [
                        'batch_id' => $idempotencyKey,
                        'finished_product_id' => $finishedGift->id,
                        'produced_quantity' => $producedQuantity,
                        'payload_hash' => $payloadHash,
                    ]
                );
            }

            /** @var Inventory $finishedInventory */
            $finishedInventory = $inventories->get((int) $finishedGift->id);
            $before = $this->balances($finishedInventory);
            $finishedInventory->quantity += $producedQuantity;
            $finishedInventory->last_restock_date = now()->toDateString();
            $finishedInventory->save();
            $this->movement(
                $finishedInventory,
                InventoryMovement::TYPE_GIFT_ASSEMBLY_PRODUCE,
                $before,
                $producedQuantity,
                $picker->id,
                $comment ?: 'Готовый подарок собран и принят на остаток',
                "{$movementPrefix}:produce",
                [
                    'batch_id' => $idempotencyKey,
                    'consumed_items' => $consumption->map(fn ($quantity, $productId): array => [
                        'product_id' => (int) $productId,
                        'quantity' => (int) $quantity,
                    ])->values()->all(),
                    'payload_hash' => $payloadHash,
                ]
            );

            return [
                'already_applied' => false,
                'batch_id' => $idempotencyKey,
                'finished_product_id' => (int) $finishedGift->id,
                'warehouse_id' => $warehouseId,
                'produced_quantity' => $producedQuantity,
                'new_quantity' => (int) $finishedInventory->quantity,
            ];
        });
    }

    private function balances(Inventory $inventory): array
    {
        return [
            'physical' => (int) $inventory->quantity,
            'online' => (int) $inventory->reserved_online_quantity,
            'seller' => (int) $inventory->reserved_seller_quantity,
        ];
    }

    private function movement(
        Inventory $inventory,
        string $type,
        array $before,
        int $physicalDelta,
        int $actorId,
        string $reason,
        string $idempotencyKey,
        array $metadata
    ): void {
        InventoryMovement::query()->create([
            'inventory_id' => $inventory->id,
            'product_id' => $inventory->product_id,
            'warehouse_id' => $inventory->warehouse_id,
            'actor_id' => $actorId,
            'type' => $type,
            'physical_delta' => $physicalDelta,
            'reserved_online_delta' => 0,
            'reserved_seller_delta' => 0,
            'physical_before' => $before['physical'],
            'physical_after' => (int) $inventory->quantity,
            'reserved_online_before' => $before['online'],
            'reserved_online_after' => (int) $inventory->reserved_online_quantity,
            'reserved_seller_before' => $before['seller'],
            'reserved_seller_after' => (int) $inventory->reserved_seller_quantity,
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata,
        ]);
    }
}
