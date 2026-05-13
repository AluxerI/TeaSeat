<?php
// database/factories/Sub_SubcategoryFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Subcategory;

class Sub_SubcategoryFactory extends Factory
{
    public function definition(): array
    {
        $subcategory = Subcategory::inRandomOrder()->first() 
            ?? Subcategory::factory()->create();

        if (str_contains($subcategory->name, 'чай')) {
            $names = ['Белый', 'Зеленый', 'Черный', 'Улун', 'Пуэр'];
            $name = $this->faker->randomElement($names) . ' чай';
        } else {
            $names = ['Классический', 'Шоколадный', 'Фруктовый', 'популярный','Солёный', 'Мармеладный', 'Цветной','Цитрусовый'];
            $name = $this->faker->randomElement($names) . ' ' . $subcategory->name;
        }

        return [
            'subcategory_id' => $subcategory->id,
            'name' => $name,
            'icon' => null,
        ];
    }
}