<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $teaTypes = ['Чай', 'Сладости', 'Для чая', 'Кофе'];
        
        return [
            'name' => $this->faker->unique()->randomElement($teaTypes),
            // 'description' => $this->faker->sentence(10),
        ];
    }
}