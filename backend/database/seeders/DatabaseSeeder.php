<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WarehouseSeeder::class,
            CategoriesSeeder::class,
            InventorySeeder::class,
            ProductSeeder::class,
            RolePermissionSeeder::class,
            PromotionSeeder::class,
            SupplierSeeder::class,
            ProductSupplierSeeder::class,
            ProductPromotionSeeder::class,
            DeliveryMethodSeeder::class
        ]);
    }
}
