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
            'Сладости' => ['Зефир', 'Шоколад', 'Мармелад', 'Фрукт'],
            'Для чая' => ['Чайники', 'Пиалы', 'Ситечки','Пушки'],
            'Кофе' => ['Зерновой', 'Молотый', 'Растворимый','Арабика'],
            'Разное' => ['Чудо','Сокровище','Магия','Волшебство'],
            'Готовые подарочные наборы' =>['маленький','большой','средний','популярный'],
            'конструктор подарков' =>['Стандартный', 'Кастомный подарок', 'подарок звезды'],
            'чайный набор' => ['Для чайных церемоний','домашний','крафтовый','популярный']
        ];

        // Сначала создаём категорию (или берём существующую)
        $category = Category::inRandomOrder()->first()
        ?? Category::factory()->create();

        return [
            'category_id' => $category->id,
            'name' => $this->faker->unique()->randomElement($subcategories[$category->name]),
        ];
    }
}
