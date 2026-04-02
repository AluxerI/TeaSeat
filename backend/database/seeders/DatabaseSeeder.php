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
            RolePermissionSeeder::class,
            DeliveryMethodSeeder::class,
            CategoriesSeeder::class,
            InventorySeeder::class,
            ProductSeeder::class,
            PromotionSeeder::class,
            SupplierSeeder::class,
            ProductSupplierSeeder::class,
            ProductPromotionSeeder::class,
            ProductImageSeeder::class,
            IconSeeder::class,
            CategoryImageSeeder::class
        ]);
    }
}
