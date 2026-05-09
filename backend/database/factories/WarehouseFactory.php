<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Склад ' . $this->faker->city,
            'city' => $this->faker->city, // Добавляем city
            'location' => $this->faker->address,
            'is_active' => $this->faker->boolean(90), // 90% активных складов
        ];
    }
    
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}