<?php
// app/Console/Commands/ClearProductImages.php

namespace App\Console\Commands;

use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearProductImages extends Command
{
    protected $signature = 'products:clear-images 
                            {--force : Без подтверждения}
                            {--keep-placeholders : Оставить заглушки}';
    
    protected $description = 'Очищает все изображения товаров из storage и БД';

    public function handle()
    {
        if (!$this->option('force')) {
            $count = ProductImage::count();
            if (!$this->confirm("Найдено {$count} записей изображений. Удалить всё?")) {
                $this->info('Операция отменена');
                return 0;
            }
        }

        // Получаем все уникальные диски
        $disks = ProductImage::distinct('disk')->pluck('disk');
        
        // Удаляем файлы
        foreach ($disks as $disk) {
            $this->info("Очищаем диск: {$disk}");
            
            $images = ProductImage::where('disk', $disk)->get();
            
            foreach ($images as $image) {
                if (Storage::disk($disk)->exists($image->path)) {
                    Storage::disk($disk)->delete($image->path);
                    $this->line("  Удалён файл: {$image->path}");
                }
            }
            
            // Удаляем пустые папки товаров
            $productFolders = Storage::disk($disk)->directories('products');
            foreach ($productFolders as $folder) {
                if (empty(Storage::disk($disk)->files($folder))) {
                    Storage::disk($disk)->deleteDirectory($folder);
                    $this->line("  Удалена папка: {$folder}");
                }
            }
        }

        // Очищаем таблицу
        $deleted = ProductImage::query()->delete();
        
        $this->info("Удалено {$deleted} записей из БД");
        
        if (!$this->option('keep-placeholders')) {
            // Удаляем папку placeholders
            Storage::disk('public')->deleteDirectory('placeholders');
            $this->info('Удалены заглушки');
        }
        
        return 0;
    }
}