<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Склад ' . $this->faker->city,
            'city' => $this->faker->city, // Добавляем city
            'location' => $this->faker->address,
            'type' => Warehouse::TYPE_WAREHOUSE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
            'is_delivery_hub' => false,
        ];
    }
    
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function physicalStore(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Warehouse::TYPE_STORE,
        ]);
    }

    public function deliveryHub(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_delivery_hub' => true,
        ]);
    }
}
