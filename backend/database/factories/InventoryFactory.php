<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    public function definition(): array
    {
        // Берем случайный товар и склад (может повторяться)
        return [
            'product_id' => Product::inRandomOrder()->first()->id,
            'warehouse_id' => Warehouse::inRandomOrder()->first()->id,
            'quantity' => $this->faker->numberBetween(0, 100), // Более реалистичное количество
            'last_restock_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    // Состояния для разных сценариев
    public function popularProduct(): static
    {
        return $this->state([
            'quantity' => $this->faker->numberBetween(50, 200),
        ]);
    }

    public function lowStock(): static
    {
        return $this->state([
            'quantity' => $this->faker->numberBetween(1, 5),
        ]);
    }
}
