<?php
// database/seeders/CategoryImageSeeder.php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CategoryImageSeeder extends Seeder
{
    protected string $tempImagesPath = 'public/temp_category_images';
    protected array $availableImages = [];

    public function run(): void
    {
        $this->command->info('Начинаем добавление изображений для категорий...');
        
        // Очищаем старые изображения
        $this->cleanOldImages();
        
        // Загружаем доступные изображения
        $this->loadAvailableImages();
        
        $categories = Category::all();
        
        if ($categories->isEmpty()) {
            $this->command->warn('Нет категорий для добавления изображений');
            return;
        }
        
        foreach ($categories as $index => $category) {
            $this->addImagesToCategory($category, $index);
        }
        
        $this->command->info('Изображения для категорий успешно добавлены!');
    }

    protected function cleanOldImages(): void
    {
        Storage::disk('public')->deleteDirectory('category-images');
        Storage::disk('public')->makeDirectory('category-images');
    }

    protected function loadAvailableImages(): void
    {
        $tempPath = Storage::disk('public')->path('temp_category_images');
        
        if (!File::exists($tempPath)) {
            $this->command->warn('Папка temp_category_images не найдена');
            return;
        }

        $categories = File::directories($tempPath);
        
        foreach ($categories as $categoryPath) {
            $categoryName = basename($categoryPath);
            $images = File::files($categoryPath);
            
            $categoryImages = [];
            foreach ($images as $image) {
                $categoryImages[] = [
                    'path' => 'temp_category_images/' . $categoryName . '/' . $image->getFilename(),
                    'name' => $image->getFilename(),
                    'type' => $this->getImageType($image->getFilename()),
                ];
            }
            
            if (!empty($categoryImages)) {
                $this->availableImages[$categoryName] = $categoryImages;
            }
        }
    }

    protected function getImageType(string $filename): string
    {
        $filename = strtolower($filename);
        
        if (str_contains($filename, 'main')) {
            return 'main';
        }
        if (str_contains($filename, 'background')) {
            return 'background';
        }
        return 'additional';
    }

    protected function addImagesToCategory(Category $category, int $index): void
    {
        $imageSets = array_keys($this->availableImages);
        if (empty($imageSets)) {
            return;
        }
        
        $setIndex = $index % count($imageSets);
        $setName = $imageSets[$setIndex];
        $images = $this->availableImages[$setName] ?? [];
        
        $hasMain = false;
        $hasBackground = false;
        
        foreach ($images as $image) {
            // Добавляем только main и background, остальные пропускаем
            if ($image['type'] === 'main' && !$hasMain) {
                $this->createCategoryImage($category, $image['path'], 'main');
                $hasMain = true;
            } elseif ($image['type'] === 'background' && !$hasBackground) {
                $this->createCategoryImage($category, $image['path'], 'background');
                $hasBackground = true;
            }
        }
    }

    protected function createCategoryImage(Category $category, string $tempPath, string $type): void
    {
        if (!Storage::disk('public')->exists($tempPath)) {
            return;
        }

        $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $path = "category-images/{$category->id}/{$filename}";
        
        Storage::disk('public')->makeDirectory("category-images/{$category->id}");
        Storage::disk('public')->copy($tempPath, $path);
        
        CategoryImage::create([
            'category_id' => $category->id,
            'path' => $path,
            'disk' => 'public',
            'is_main' => ($type === 'main'),
            'is_background' => ($type === 'background'),
            'alt' => $category->name . ($type === 'main' ? ' - главное' : ' - фон'),
            'title' => $category->name,
        ]);
    }
}