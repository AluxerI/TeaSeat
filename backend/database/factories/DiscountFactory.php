<?php

namespace Database\Factories;

use App\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        $types = ['personal', 'first_order', 'loyalty', 'referral', 'promotion', 'cart', 'shipping'];
        $hasCode = $this->faker->boolean(30);
        
        return [
            'name' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'value' => $this->faker->numberBetween(5, 50),
            'value_type' => Discount::VALUE_PERCENT,
            'type' => $this->faker->randomElement($types),
            'is_active' => $this->faker->boolean(80),
            'code' => $hasCode ? $this->faker->unique()->bothify('PROMO-####') : null,
            'is_global' => $this->faker->boolean(20),
            'min_order_amount' => $this->faker->optional()->numberBetween(500, 5000),
            'usage_limit' => $this->faker->optional()->numberBetween(10, 500),
            'usage_per_user' => $this->faker->optional()->numberBetween(1, 5),
            'start_date' => $this->faker->optional()->dateTimeBetween('-30 days', '+30 days'),
            'end_date' => $this->faker->optional()->dateTimeBetween('+31 days', '+90 days'),
            'created_by' => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(25),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(5),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(40),
        ]);
    }

    public function withCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $this->faker->unique()->bothify('PROMO-####'),
        ]);
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_global' => true,
        ]);
    }
}
