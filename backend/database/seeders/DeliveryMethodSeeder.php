<?php

namespace Database\Seeders;      

use App\Models\DeliveryMethod;
use App\Models\DeliveryTimeSlot;
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
                'available_cities' => ['Москва', 'Санкт-Петербург', 'Тула'],
                'type' => DeliveryMethod::TYPE_COURIER,
            ],
            [
                'name' => 'Самовывоз',
                'description' => 'Самовывоз из пункта выдачи',
                'cost' => 0.00,
                'estimated_days_min' => 0,
                'estimated_days_max' => 1,
                'is_active' => true,
                'available_cities' => ['Москва', 'Санкт-Петербург', 'Новосибирск', 'Екатеринбург', 'Тула'],
                'type' => DeliveryMethod::TYPE_PICKUP,
            ],
            [
                'name' => 'Почта России',
                'description' => 'Доставка почтой России',
                'cost' => 200.00,
                'estimated_days_min' => 5,
                'estimated_days_max' => 14,
                'is_active' => true,
                'available_cities' => null,
                'type' => DeliveryMethod::TYPE_EXTERNAL,
                'provider_code' => 'russian_post',
            ],
            [
                'name' => 'Экспресс-доставка',
                'description' => 'Срочная доставка в течение дня',
                'cost' => 600.00,
                'estimated_days_min' => 1,
                'estimated_days_max' => 1,
                'is_active' => true,
                'available_cities' => ['Москва', 'Санкт-Петербург'],
                'type' => DeliveryMethod::TYPE_EXPRESS,
            ]
        ];

        foreach ($methods as $method) {
            $deliveryMethod = DeliveryMethod::updateOrCreate(
                ['name' => $method['name']],
                $method
            );

            $schedule = match ($deliveryMethod->type) {
                DeliveryMethod::TYPE_COURIER => [
                    'weekdays' => [1, 3, 5],
                    'capacity' => 5,
                ],
                DeliveryMethod::TYPE_EXPRESS => [
                    'weekdays' => [1, 2, 3, 4, 5, 6],
                    'capacity' => 3,
                ],
                default => null,
            };

            if ($schedule !== null) {
                foreach ($schedule['weekdays'] as $weekday) {
                    foreach ([['10:00', '14:00'], ['14:00', '18:00']] as $time) {
                        DeliveryTimeSlot::updateOrCreate([
                            'delivery_method_id' => $deliveryMethod->id,
                            'weekday' => $weekday,
                            'time_from' => $time[0],
                            'time_to' => $time[1],
                        ], [
                            'capacity' => $schedule['capacity'],
                            'is_active' => true,
                        ]);
                    }
                }
            }
        }

        $this->command->info('Настроено ' . count($methods) . ' методов доставки');
    }
}
