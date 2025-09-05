<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+3 months');
        
        return [
            'name' => $this->faker->randomElement([
                'Летняя распродажа',
                'Зимняя акция',
                'Весенние скидки',
                'Осенняя коллекция',
                'Черная пятница',
                'Киберпонедельник',
                'Новогодние предложения',
                'Сезонная распродажа',
                'Флеш-скидки',
                'Специальное предложение'
            ]),
            'description' => $this->faker->sentence(10),
            'discount_percent' => $this->faker->randomFloat(2, 5, 50),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subDays(rand(1, 30)),
            'end_date' => now()->addDays(rand(30, 90)),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->addDays(rand(10, 60)),
            'end_date' => now()->addDays(rand(90, 180)),
        ]);
    }
}
