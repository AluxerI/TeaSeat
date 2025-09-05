<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductPromotionSeeder extends Seeder
{
    public function run(): void
    {
        // Очищаем таблицу перед заполнением
        DB::table('product_promotions')->delete();

        $products = Product::all();
        $promotions = Promotion::where('is_active', true)->get();

        // Для каждой активной акции добавляем от 3 до 10 товаров
        foreach ($promotions as $promotion) {
            $randomProducts = $products->random(rand(3, 10));
            
            foreach ($randomProducts as $product) {
                DB::table('product_promotions')->insert([
                    'product_id' => $product->id,
                    'promotion_id' => $promotion->id,
                ]);
            }
        }
    }
}
