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
            UserSeeder::class,
            CategoriesSeeder::class,
            InventorySeeder::class,
            ProductSeeder::class,
            SupplierSeeder::class,
            ProductSupplierSeeder::class,
            ProductImageSeeder::class,
            IconSeeder::class,
            CategoryImageSeeder::class,
            DiscountSeeder::class,
        ]);
    }
}
