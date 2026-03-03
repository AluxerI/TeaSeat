<?php
// database/factories/CategoryImageFactory.php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'path' => 'temp_category_images/placeholder.jpg',
            'disk' => 'public',
            'sort_order' => 0,
            'is_main' => false,
            'is_background' => false,
            'alt' => $this->faker->sentence(3),
            'title' => $this->faker->sentence(2),
        ];
    }

    /**
     * Состояние: главное изображение
     */
    public function main()
    {
        return $this->state(fn (array $attributes) => [
            'is_main' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * Состояние: фоновое изображение
     */
    public function background()
    {
        return $this->state(fn (array $attributes) => [
            'is_background' => true,
            'sort_order' => 1,
        ]);
    }
}