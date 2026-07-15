<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        // Сначала создаем продукты, если их нет
        if (Product::count() === 0) {
            Product::factory()->count(150)->create();
        }
        
        $products = Product::all();
        $warehouses = Warehouse::all();

        $inventories = [];

        foreach ($products as $product) {
            // Для каждого товара создаем инвентарь на случайных складах
            $randomWarehouses = $warehouses->random(rand(0, 4)); // От 0 до 4 случайных складов
            
            foreach ($randomWarehouses as $warehouse) {
                $quantity = rand(0, 100);
                $inventories[] = [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => $quantity,
                    'reserved_online_quantity' => 0,
                    'reserved_seller_quantity' => 0,
                    'last_restock_date' => $quantity > 0 ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                // Обновляем total_quantity в продукте
                $product->total_quantity = ($product->total_quantity ?? 0) + $quantity;
                $product->is_available = $product->total_quantity > 0;
            }
            $product->save();
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
