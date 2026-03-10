<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    private static $warehouses;
    private static $products;
    private static $usedPairs = [];

    public function definition(): array
    {
        if (!self::$warehouses) {
            self::$warehouses = Warehouse::inRandomOrder()->limit(5)->pluck('id')->toArray();
            // Если складов нет, создаем
            if (empty(self::$warehouses)) {
                self::$warehouses = Warehouse::factory()->count(5)->create()->pluck('id')->toArray();
            }
        }

        if (!self::$products) {
            self::$products = Product::inRandomOrder()->limit(20)->pluck('id')->toArray();
            if (empty(self::$products)) {
                self::$products = Product::factory()->count(20)->create()->pluck('id')->toArray();
            }
        }

        do {
            $productId = $this->faker->randomElement(self::$products);
            $warehouseId = $this->faker->randomElement(self::$warehouses);
            $pair = "{$productId}_{$warehouseId}";
        } while (in_array($pair, self::$usedPairs));

        self::$usedPairs[] = $pair;
        
        $quantity = $this->faker->numberBetween(0, 100);

        return [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'last_restock_date' => $quantity > 0 
                ? $this->faker->dateTimeBetween('-1 year', 'now') 
                : null,
        ];
    }
    
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->numberBetween(1, 9),
        ]);
    }
    
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'last_restock_date' => null,
        ]);
    }
}