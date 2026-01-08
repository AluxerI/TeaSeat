<?php

namespace App\Services;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Получить доступное количество товара на складе
     */
    public function getAvailableQuantity(int $productId, int $warehouseId): int
    {
        $inventory = DB::table('inventories')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $inventory ? $inventory->quantity : 0;
    }

    /**
     * Определить подходящий склад для товара
     */
    public function determineWarehouse(int $productId): int
    {
        // 1. Ищем склад с наибольшим количеством
        $inventory = DB::table('inventories')
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->first();

        if ($inventory) {
            return $inventory->warehouse_id;
        }

        // 2. Ищем любой склад с этим товаром
        $anyInventory = DB::table('inventories')
            ->where('product_id', $productId)
            ->first();

        if ($anyInventory) {
            return $anyInventory->warehouse_id;
        }

        // 3. Возвращаем склад по умолчанию
        $defaultWarehouse = Warehouse::first();
        
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