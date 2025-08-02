<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true), // "Элитный Зеленый Чай"
            'description' => $this->faker->paragraph,
            'brand_id' => Brand::factory(), // Связь с брендом
            'price' => $this->faker->numberBetween(100, 5000), // Случайная цена (100-5000 руб.)
            'weight_grams' => $this->faker->numberBetween(50, 1000), // Вес в граммах
            'sold_count' => $this->faker->numberBetween(0, 1000), // Количество продаж
        ];
    }

    // Дополнительные состояния (например, "распродажа")
    public function onSale(): static
    {
        return $this->state([
            'price' => $this->faker->numberBetween(50, 500), // Сниженная цена
        ]);
    }
}
