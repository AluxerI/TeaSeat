<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Sub_subcategory;
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
    public function configure()
    {
        return $this->afterCreating(function (Product $product) {
            // Создаем минимум одну связь с под-подкатегорией
            $subSubcategory = Sub_subcategory::inRandomOrder()->first() 
                ?? Sub_subcategory::factory()->create();
            
            $product->sub_subcategories()->attach($subSubcategory);

            // Опционально: добавляем случайное количество дополнительных связей
            if ($this->faker->boolean(30)) { // 30% вероятности добавить еще связи
                $count = $this->faker->numberBetween(1, 3);
                $additionalSubSubcategories = Sub_subcategory::inRandomOrder()
                    ->whereNotIn('id', [$subSubcategory->id])
                    ->take($count)
                    ->get();
                
                $product->sub_subcategories()->attach($additionalSubSubcategories);
            }
        });
    }
}
