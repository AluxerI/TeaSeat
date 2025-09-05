<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Subcategory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sub_subcategory>
 */
class Sub_SubcategoryFactory extends Factory
{
    public function definition(): array
    {
        // Создаем подкатегорию, если не передана
        $subcategory = subcategory::inRandomOrder()->first() 
                ?? subcategory::factory()->create();

        // Для чайных подкатегорий
        if (str_contains($subcategory->name, 'чай')) {
            $names = ['Белый', 'Зеленый', 'Черный', 'Улун', 'Пуэр'];
            $name = $this->faker->randomElement($names) . ' чай';
        } 
        // Для сладостей
        else {
            $names = ['Классическое', 'Шоколадное', 'Фруктовое', 'Хорошее','Плохое', 'Сойдёт', 'Ладно','Чёрное'];
            $name = $this->faker->randomElement($names) . ' ' . $subcategory->name;
        }

        return [
            'subcategory_id' => $subcategory->id,
            'name' => $name,
        ];
    }
}
