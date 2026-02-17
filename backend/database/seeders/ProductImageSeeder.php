<?php
// database/seeders/ProductImageSeeder.php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageSeeder extends Seeder
{
    protected $targetDisk = 'public';
    
    public function run(): void
    {
        $this->command->info('Начинаем добавление изображений к товарам...');
        
        $products = Product::all();
        
        if ($products->isEmpty()) {
            $this->command->error('Нет товаров! Сначала создайте товары.');
            return;
        }
        
        // ОЧИЩАЕМ старые изображения и папки
        $this->cleanOldImages();
        
        // Путь к исходным изображениям в storage
        $sourcePath = storage_path('app/public/temp_sources');
        
        // Проверяем, есть ли папка с наборами
        if (!File::exists($sourcePath)) {
            $this->command->error("Папка с изображениями не найдена: {$sourcePath}");
            $this->command->warn('Создайте папку storage/app/public/temp_sources/ и положите туда 10 папок с изображениями');
            return;
        }
        
        // Получаем все подпапки с наборами
        $setFolders = File::directories($sourcePath);
        
        if (empty($setFolders)) {
            $this->command->error('В папке temp_sources нет подпапок с наборами изображений');
            return;
        }
        
        $this->command->info('Найдено наборов: ' . count($setFolders));
        
        // Создаём папку для изображений товаров
        Storage::disk($this->targetDisk)->makeDirectory('products');
        
        foreach ($products as $index => $product) {
            // Выбираем набор по кругу
            $setIndex = $index % count($setFolders);
            $setFolder = $setFolders[$setIndex];
            
            // Получаем все изображения из набора
            $images = File::files($setFolder);
            
            if (empty($images)) {
                $this->command->warn("Набор {$setFolder} пуст, пропускаем");
                continue;
            }
            
            $this->addImagesToProduct($product, $images);
            
            if (($index + 1) % 50 == 0) {
                $this->command->line('Обработано ' . ($index + 1) . ' товаров...');
            }
        }
        
        $this->command->info('Готово! Изображения добавлены для ' . $products->count() . ' товаров.');
    }
    
    /**
     * Очищает старые изображения и папки
     */
    protected function cleanOldImages(): void
    {
        $this->command->warn('Очистка старых изображений...');
        
        // Удаляем ВСЮ папку products целиком
        Storage::disk($this->targetDisk)->deleteDirectory('products');
        
        // Очищаем таблицу
        ProductImage::query()->delete();
        
        // Создаём папку заново
        Storage::disk($this->targetDisk)->makeDirectory('products');
        
        $this->command->info('Очистка завершена');
    }
    
    /**
     * Добавляет изображения к товару
     */
    protected function addImagesToProduct($product, $images)
    {
        $productFolder = "products/{$product->id}";
        Storage::disk($this->targetDisk)->makeDirectory($productFolder);
        
        $sortOrder = 0;
        
        foreach ($images as $imageFile) {
            // Генерируем уникальное имя файла
            $extension = $imageFile->getExtension();
            $uniqueName = time() . '_' . Str::random(6) . '_' . $sortOrder . '.' . $extension;
            $destinationPath = "{$productFolder}/{$uniqueName}";
            
            // Копируем файл в storage
            Storage::disk($this->targetDisk)->put(
                $destinationPath,
                File::get($imageFile)
            );
            
            // Определяем флаги
            $isMain = ($sortOrder === 0); // Первое изображение - главное
            $isBackground = ($sortOrder === 1); // Второе - фоновое
            
            // Создаем запись в БД
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $destinationPath,
                'disk' => $this->targetDisk,
                'sort_order' => $sortOrder,
                'is_main' => $isMain,
                'is_background' => $isBackground,
                'alt' => $this->generateAlt($product, $isMain, $isBackground, $sortOrder),
                'title' => $product->name,
            ]);
            
            $sortOrder++;
        }
    }
    
    /**
     * Генерирует alt текст
     */
    protected function generateAlt($product, $isMain, $isBackground, $index)
    {
        if ($isMain) {
            return "Главное изображение {$product->name}";
        }
        if ($isBackground) {
            return "Фоновое изображение {$product->name}";
        }
        return "Изображение " . ($index + 1) . " товара {$product->name}";
    }
}