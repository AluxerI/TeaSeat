<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;


class SubcategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(), // Связь с категорией
            'name' => $this->faker->unique()->word, // "Зеленый", "Черный" и т.д.
        ];
    }
}
