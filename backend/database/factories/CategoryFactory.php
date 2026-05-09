<?php
// database/factories/CategoryFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $teaTypes = ['Чай', 'Сладости', 'Для чая', 'Кофе','Разное','Готовые подарочные наборы','конструктор подарков','чайный набор'];
        
        return [
            'name' => $this->faker->unique()->randomElement($teaTypes),
            'icon' => null, // Будет заполнено в сидере
        ];
    }
}