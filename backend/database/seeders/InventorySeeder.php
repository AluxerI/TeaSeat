<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::factory()
            ->count(150)
            ->create();
        $products = Product::all();
        $warehouses = Warehouse::all();

        $inventories = [];

        foreach ($products as $product) {
            // Для каждого товара создаем инвентарь на случайных складах
            $randomWarehouses = $warehouses->random(rand(0, 4)); // От 2 до 4 случайных складов
            
            foreach ($randomWarehouses as $warehouse) {
                $inventories[] = [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => rand(10, 100),
                    'last_restock_date' => now()->subDays(rand(1, 30)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Разбиваем на партии для избежания memory limit
        foreach (array_chunk($inventories, 100) as $chunk) {
            Inventory::insert($chunk);
        }

        $this->command->info('Создано ' . count($inventories) . ' записей инвентаря');
        
        // Статистика по городам
        $cityStats = DB::table('inventories')
            ->join('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id')
            ->select('warehouses.city', DB::raw('COUNT(*) as product_count'))
            ->groupBy('warehouses.city')
            ->get();

        $this->command->info('Распределение товаров по городам:');
        foreach ($cityStats as $stat) {
            $this->command->info("  {$stat->city}: {$stat->product_count} товаров");
        }
    }
}
