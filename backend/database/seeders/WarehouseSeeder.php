<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            'Москва' => [
                [
                    'name' => 'Склад Москва Центральный',
                    'location' => 'ул. Тверская, д. 10',
                    'type' => Warehouse::TYPE_WAREHOUSE,
                    'is_active' => true,
                ],
            ],
            'Санкт-Петербург' => [
                [
                    'name' => 'Склад СПб Северный', 
                    'location' => 'пр. Энгельса, д. 15',
                    'type' => Warehouse::TYPE_WAREHOUSE,
                    'is_active' => true,
                ]
            ],
            'Новосибирск' => [
                [
                    'name' => 'Склад Новосибирск Главный',
                    'location' => 'ул. Ленина, д. 5',
                    'type' => Warehouse::TYPE_WAREHOUSE,
                    'is_active' => true,
                ]
            ],
            'Тула' => [
                [
                    'name' => 'Склад Тула Главный',
                    'location' => 'ул. Луначарского, д. 30',
                    'type' => Warehouse::TYPE_WAREHOUSE,
                    'is_active' => true,
                ],
                [
                    'name' => 'Магазин Тула Южный',
                    'location' => 'ул. Профсоюзная, д. 25',
                    'type' => Warehouse::TYPE_STORE,
                    'is_active' => true,
                ]
            ]
        ];

        foreach ($cities as $city => $warehouses) {
            foreach ($warehouses as $warehouse) {
                Warehouse::create([
                    'name' => $warehouse['name'],
                    'city' => $city,
                    'location' => $warehouse['location'],
                    'type' => $warehouse['type'],
                    'is_active' => $warehouse['is_active'],
                    'is_online_fulfillment_enabled' => true,
                ]);
            }
        }

        $this->command->info('Создано ' . Warehouse::count() . ' точек хранения в 4 городах');
        $this->command->info('Города: ' . implode(', ', array_keys($cities)));
        $this->command->info('В Туле склад и магазин, в остальных городах по одному складу');
    }
}
