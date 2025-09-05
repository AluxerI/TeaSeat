<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $teaTypes = ['Чай', 'Сладости', 'Для чая', 'Кофе','Разное','Готовые подарочные наборы','Первое что-то ещё','Второе что-то ещё'];
        
        return [
            'name' => $this->faker->unique()->randomElement($teaTypes),
        ];
    }
}