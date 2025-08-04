<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    private static $warehouses;
    private static $products;
    private static $usedPairs = [];

    public function definition(): array
    {
        // Инициализация складов (ровно 5)
        if (!self::$warehouses) {
            self::$warehouses = Warehouse::factory()
                ->count(5)
                ->create()
                ->pluck('id')
                ->toArray();
        }

        // Инициализация товаров (20 штук)
        if (!self::$products) {
            self::$products = Product::factory()
                ->count(20)
                ->create()
                ->pluck('id')
                ->toArray();
        }

        // Генерация уникальной пары товар-склад
        do {
            $productId = $this->faker->randomElement(self::$products);
            $warehouseId = $this->faker->randomElement(self::$warehouses);
            $pair = "{$productId}_{$warehouseId}";
        } while (in_array($pair, self::$usedPairs));

        self::$usedPairs[] = $pair;

        return [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $this->faker->numberBetween(1, 100),
            'last_restock_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
