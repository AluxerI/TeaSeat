<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Sub_subcategory;
use App\Models\Product;

class Sub_SubcategoryProductFactory extends Factory
{
    public function definition(): array
    {
        // Берём существующие записи или создаём новые БЕЗ рекурсии
        return [
            'sub_subcategory_id' => Sub_Subcategory::inRandomOrder()->first()->id 
                ?? Sub_Subcategory::factory()->create()->id,
            'product_id' => Product::inRandomOrder()->first()->id 
                ?? Product::factory()->create()->id,
        ];
    }
}
