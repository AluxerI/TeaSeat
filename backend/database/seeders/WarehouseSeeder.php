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
                    'is_active' => true,
                ],
            ],
            'Санкт-Петербург' => [
                [
                    'name' => 'Склад СПб Северный', 
                    'location' => 'пр. Энгельса, д. 15',
                    'is_active' => true,
                ]
            ],
            'Новосибирск' => [
                [
                    'name' => 'Склад Новосибирск Главный',
                    'location' => 'ул. Ленина, д. 5',
                    'is_active' => true,
                ]
            ],
            'Тула' => [
                [
                    'name' => 'Склад Тула Главный',
                    'location' => 'ул. Луначарского, д. 30',
                    'is_active' => true,
                ],
                [
                    'name' => 'Склад Тула Южный',
                    'location' => 'ул. Профсоюзная, д. 25',
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
                    'is_active' => $warehouse['is_active'],
                ]);
            }
        }

        $this->command->info('Создано ' . Warehouse::count() . ' складов в 4 городах');
        $this->command->info('Города: ' . implode(', ', array_keys($cities)));
        $this->command->info('В Туле 2 склада, в остальных городах по 1 складу');
    }
}