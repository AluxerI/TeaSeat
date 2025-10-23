<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSupplierSeeder extends Seeder
{
    public function run(): void
    {
        // Очищаем таблицу перед заполнением
        DB::table('product_supplier')->truncate();

        $products = Product::all();
        $suppliers = Supplier::all();

        if ($products->isEmpty() || $suppliers->isEmpty()) {
            $this->command->error('Нет продуктов или поставщиков для создания связей');
            return;
        }

        $connections = [];

        foreach ($products as $product) {
            // Каждый товар привязываем к 1-3 случайным поставщикам
            $productSuppliers = $suppliers->random(rand(1, 3));
            
            foreach ($productSuppliers as $supplier) {
                $costPrice = $this->calculateCostPrice($product->price);
                $leadTime = rand(3, 14);
                $minOrder = rand(1, 10);

                $connections[] = [
                    'product_id' => $product->id,
                    'supplier_id' => $supplier->id,
                    'cost_price' => $costPrice,
                    'lead_time_days' => $leadTime,
                    'min_order_quantity' => $minOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Вставляем пачкой для производительности
        DB::table('product_supplier')->insert($connections);

        $this->command->info("Создано " . count($connections) . " связей товар-поставщик");
        
        // Статистика
        $stats = DB::table('product_supplier')
            ->select(DB::raw('COUNT(*) as total, supplier_id'))
            ->groupBy('supplier_id')
            ->get();

        $this->command->info("Статистика по поставщикам:");
        foreach ($stats as $stat) {
            $supplier = Supplier::find($stat->supplier_id);
            $this->command->info(" - {$supplier->name}: {$stat->total} товаров");
        }
    }

    /**
     * Рассчитать закупочную цену (60-80% от розничной)
     */
    private function calculateCostPrice(float $retailPrice): float
    {
        $margin = rand(60, 80) / 100; // 60-80% от розничной цены
        return round($retailPrice * $margin, 2);
    }
}