<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductPromotionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::inRandomOrder()->first()->id ?? Product::factory(),
            'promotion_id' => Promotion::inRandomOrder()->first()->id ?? Promotion::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}