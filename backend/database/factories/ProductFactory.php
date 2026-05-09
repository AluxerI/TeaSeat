<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Sub_subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $totalQuantity = $this->faker->numberBetween(0, 1000);
        
        return [
            'name' => $this->faker->unique()->words(3, true),
            'ingredients' => $this->faker->unique()->words(15, true),
            'description' => $this->faker->paragraph(),
            'brand_id' => Brand::factory(),
            'price' => $this->faker->numberBetween(100, 5000),
            'weight_grams' => $this->faker->numberBetween(50, 1000),
            'sold_count' => $this->faker->numberBetween(0, 1000),
            // Добавляем поля для кеширования
            'total_quantity' => $totalQuantity,
            'is_available' => $totalQuantity > 0,
            'cached_data' => null,
        ];
    }

    public function onSale(): static
    {
        return $this->state([
            'price' => $this->faker->numberBetween(50, 500),
        ]);
    }
    
    public function outOfStock(): static
    {
        return $this->state([
            'total_quantity' => 0,
            'is_available' => false,
        ]);
    }

    public function configure()
    {
        return $this->afterCreating(function (Product $product) {
            $subSubcategory = Sub_subcategory::inRandomOrder()->first() 
                ?? Sub_subcategory::factory()->create();
            
            $product->sub_subcategories()->attach($subSubcategory);

            if ($this->faker->boolean(30)) {
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