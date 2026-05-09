<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;

class WarmupCache extends Command
{
    protected $signature = 'cache:warmup';
    protected $description = 'Предзаполнение кеша для всех моделей';

    public function handle()
    {
        $this->info('Начинаем предзаполнение кеша...');

        // Прогреваем бренды
        $this->info('Прогрев брендов...');
        $brands = Brand::all();
        $bar = $this->output->createProgressBar($brands->count());
        foreach ($brands as $brand) {
            $brand->getAllData();
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // Прогреваем категории
        $this->info('Прогрев категорий...');
        $categories = Category::all();
        $bar = $this->output->createProgressBar($categories->count());
        foreach ($categories as $category) {
            $category->getAllData();
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // Прогреваем товары (только первые 1000, чтобы не перегружать)
        $this->info('Прогрев товаров (первые 1000)...');
        $products = Product::limit(1000)->get();
        $bar = $this->output->createProgressBar($products->count());
        foreach ($products as $product) {
            $product->getAllData();
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info('Кеш успешно прогрет!');
    }
}