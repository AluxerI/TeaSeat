<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        // Создаем 10 активных акций
        Promotion::factory()
            ->count(10)
            ->active()
            ->create();

        // Создаем 5 неактивных акций
        Promotion::factory()
            ->count(5)
            ->inactive()
            ->create();

        // Создаем 3 будущие акции
        Promotion::factory()
            ->count(3)
            ->future()
            ->create();
    }
}
