<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Warehouse;

class InventoryService
{
    /**
     * Получить доступное количество товара на складе
     */
    public function getAvailableQuantity(int $productId, int $warehouseId): int
    {
        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->onlineFulfillment()
            ->first();

        return $inventory?->availableQuantity() ?? 0;
    }

    /**
     * Определить подходящий склад для товара
     */
    public function determineWarehouse(int $productId): int
    {
        // 1. Ищем склад с наибольшим количеством
        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->availableForOnline()
            ->orderByRaw(Inventory::ONLINE_AVAILABLE_EXPRESSION . ' DESC')
            ->first();

        if ($inventory) {
            return $inventory->warehouse_id;
        }

        // 2. Ищем любой склад с этим товаром
        $anyInventory = Inventory::query()
            ->where('product_id', $productId)
            ->onlineFulfillment()
            ->first();

        if ($anyInventory) {
            return $anyInventory->warehouse_id;
        }

        // 3. Возвращаем склад по умолчанию
        $defaultWarehouse = Warehouse::query()
            ->onlineFulfillment()
            ->orderByRaw("CASE WHEN type = 'warehouse' THEN 0 ELSE 1 END")
            ->first();
        
        if ($defaultWarehouse) {
            return $defaultWarehouse->id;
        }

        throw new \Exception('Не найден подходящий склад для товара');
    }

    /**
     * Проверить доступность товара
     */
    public function checkAvailability(int $productId, int $warehouseId, int $quantity): bool
    {
        $available = $this->getAvailableQuantity($productId, $warehouseId);
        return $available >= $quantity;
    }
}
