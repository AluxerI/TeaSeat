<?php

namespace Database\Seeders;

use App\Models\Discount;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        // 1. АКЦИИ (type = 'promotion')
        
        // Глобальная акция на все товары
        Discount::factory()->create([
            'name' => 'Летняя распродажа',
            'description' => 'Скидка 15% на все товары',
            'value' => 15,
            'type' => 'promotion',
            'is_global' => true,
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);
        
        // Акция на категорию "Чай"
        $teaCategory = Category::where('name', 'Чай')->first();
        if ($teaCategory) {
            $categoryPromo = Discount::factory()->create([
                'name' => 'Чайная неделя',
                'description' => 'Скидка 10% на весь чай',
                'value' => 10,
                'type' => 'promotion',
                'is_global' => false,
                'is_active' => true,
                'start_date' => now(),
                'end_date' => now()->addDays(14),
            ]);
            $categoryPromo->categories()->attach($teaCategory->id);
        }
        
        // Акция на конкретные товары
        $products = Product::inRandomOrder()->limit(5)->get();
        if ($products->isNotEmpty()) {
            $productPromo = Discount::factory()->create([
                'name' => 'Хиты продаж',
                'description' => 'Скидка 20% на популярные товары',
                'value' => 20,
                'type' => 'promotion',
                'is_global' => false,
                'is_active' => true,
            ]);
            foreach ($products as $product) {
                $productPromo->products()->attach($product->id);
            }
        }
        
        // Акция с промокодом на корзину
        Discount::factory()->create([
            'name' => 'Промокод TEA2024',
            'description' => 'Скидка 10% на заказ от 1000₽',
            'value' => 10,
            'type' => 'cart',
            'code' => 'TEA2024',
            'is_active' => true,
            'min_order_amount' => 1000,
            'usage_limit' => 100,
            'start_date' => now(),
            'end_date' => now()->addDays(60),
        ]);
        
        // 2. ПЕРСОНАЛЬНЫЕ СКИДКИ (type = 'personal')
        
        // Скидка на первый заказ
        Discount::factory()->create([
            'name' => 'Скидка новичка',
            'description' => '10% на первый заказ',
            'value' => 10,
            'type' => 'first_order',
            'is_global' => true,
            'is_active' => true,
        ]);
        
        // Персональная скидка на категорию
        if ($teaCategory) {
            $personalDiscount = Discount::factory()->create([
                'name' => 'Чайный гурман',
                'description' => 'Персональная скидка 15% на чай',
                'value' => 15,
                'type' => 'personal',
                'is_global' => false,
                'is_active' => true,
            ]);
            $personalDiscount->categories()->attach($teaCategory->id);
        }
        
        // 3. НЕАКТИВНЫЕ И БУДУЩИЕ АКЦИИ
        
        // Неактивная акция
        Discount::factory()->create([
            'name' => 'Прошлая акция',
            'description' => 'Уже не действует',
            'value' => 25,
            'type' => 'promotion',
            'is_active' => false,
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(30),
        ]);
        
        // Будущая акция
        Discount::factory()->create([
            'name' => 'Новогодняя распродажа',
            'description' => 'Скидка 30%',
            'value' => 30,
            'type' => 'promotion',
            'is_active' => true,
            'start_date' => now()->addDays(30),
            'end_date' => now()->addDays(60),
        ]);
    }
}