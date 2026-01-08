<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Чайная плантация "Зеленый лист"',
                'contact_person' => 'Иван Петров',
                'email' => 'tea.greenleaf@mail.ru',
                'phone' => '+7 (495) 123-45-67',
                'city' => 'Москва',
                'address' => 'ул. Чайная, д. 15',
                'order_schedule' => json_encode(['days' => [5, 15, 25], 'type' => 'monthly']),
                'lead_time_days' => 5,
                'min_order_quantity' => 10,
                'consolidation_period' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Кофейные зерна "Арабика"',
                'contact_person' => 'Мария Сидорова',
                'email' => 'coffee.arabica@gmail.com',
                'phone' => '+7 (495) 234-56-78',
                'address' => 'г. Санкт-Петербург, Невский пр-т, д. 100',
                'order_schedule' => json_encode(['days' => [10, 20], 'type' => 'monthly']),
                'lead_time_days' => 7,
                'min_order_quantity' => 5,
                'consolidation_period' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Травяные сборы "Здоровье"',
                'contact_person' => 'Анна Козлова',
                'email' => 'herbs.health@yandex.ru',
                'phone' => '+7 (495) 345-67-89',
                'city' => 'Тула',
                'address' => 'ул. Ленина, д. 50',
                'order_schedule' => json_encode(['days' => [8, 18, 28], 'type' => 'monthly']),
                'lead_time_days' => 3,
                'min_order_quantity' => 15,
                'consolidation_period' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Элитные чаи "Императорский"',
                'contact_person' => 'Сергей Волков',
                'email' => 'tea.imperial@mail.ru',
                'phone' => '+7 (495) 456-78-90',
                'city' => 'Сочи',
                'address' => ' ул. Курортная, д. 25',
                'order_schedule' => json_encode(['days' => [1, 11, 21], 'type' => 'monthly']),
                'lead_time_days' => 10,
                'min_order_quantity' => 2,
                'consolidation_period' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Фруктовые добавки "Вкуслет"',
                'contact_person' => 'Ольга Новикова',
                'email' => 'fruits.tastelet@gmail.com',
                'phone' => '+7 (495) 567-89-01',
                'city' => 'Краснодар',
                'address' => 'ул. Фруктовая, д. 8',
                'order_schedule' => json_encode(['days' => [12, 27], 'type' => 'monthly']),
                'lead_time_days' => 6,
                'min_order_quantity' => 20,
                'consolidation_period' => 2,
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }

        $this->command->info('Создано ' . count($suppliers) . ' поставщиков');
    }
}