<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->call([
            // ProductSeeder::class,
            // WarehouseSeeder::class,
            CategoriesSeeder::class,
            InventorySeeder::class,
            ProductSeeder::class,
            RolePermissionSeeder::class,
            PromotionSeeder::class,
            ProductPromotionSeeder::class
        ]);
    }
}
