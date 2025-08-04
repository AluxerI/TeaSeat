<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;


class SubcategoryFactory extends Factory
{
    public function definition(): array
    {
        // Группируем подкатегории по родительским категориям
        $subcategories = [
            'Чай' => ['Китайский чай', 'Японский чай', 'Индийский чай', 'Травяной чай'],
            'Сладости' => ['Печенье', 'Шоколад', 'Мармелад', 'Халва'],
            'Для чая' => ['Чайники', 'Пиалы', 'Ситечки'],
            'Кофе' => ['Зерновой', 'Молотый']
        ];

        // Сначала создаём категорию (или берём существующую)
        $category = Category::factory()->create();

        return [
            'category_id' => $category->id,
            'name' => $this->faker->unique()->randomElement($subcategories[$category->name]),
        ];
    }
}
