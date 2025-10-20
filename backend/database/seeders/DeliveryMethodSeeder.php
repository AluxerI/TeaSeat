<?php

namespace Database\Seeders;      

use App\Models\DeliveryMethod;
use Illuminate\Database\Seeder;

class DeliveryMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Курьерская доставка',
                'description' => 'Доставка курьером до двери',
                'cost' => 300.00,
                'estimated_days_min' => 1,
                'estimated_days_max' => 3,
                'is_active' => true,
                'available_cities' => ['Москва', 'Санкт-Петербург', 'Тула']
            ],
            [
                'name' => 'Самовывоз',
                'description' => 'Самовывоз из пункта выдачи',
                'cost' => 0.00,
                'estimated_days_min' => 0,
                'estimated_days_max' => 1,
                'is_active' => true,
                'available_cities' => ['Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Тула']
            ],
            [
                'name' => 'Почта России',
                'description' => 'Доставка почтой России',
                'cost' => 200.00,
                'estimated_days_min' => 5,
                'estimated_days_max' => 14,
                'is_active' => true,
                'available_cities' => null 
            ],
            [
                'name' => 'Экспресс-доставка',
                'description' => 'Срочная доставка в течение дня',
                'cost' => 600.00,
                'estimated_days_min' => 1,
                'estimated_days_max' => 1,
                'is_active' => true,
                'available_cities' => ['Москва', 'Санкт-Петербург']
            ]
        ];

        foreach ($methods as $method) {
            DeliveryMethod::create($method);
        }

        $this->command->info('Создано ' . count($methods) . ' методов доставки');
    }
}