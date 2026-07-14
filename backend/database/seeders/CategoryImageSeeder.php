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
    protected string $sourceImagesPath = 'seeders/assets/category-images';
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
        $sourcePath = database_path($this->sourceImagesPath);
        
        if (!File::isDirectory($sourcePath)) {
            $this->command->warn('Папка database/seeders/assets/category-images не найдена');
            return;
        }

        $categories = File::directories($sourcePath);
        
        foreach ($categories as $categoryPath) {
            $categoryName = basename($categoryPath);
            $images = File::files($categoryPath);
            
            $categoryImages = [];
            foreach ($images as $image) {
                $categoryImages[] = [
                    'path' => $image->getPathname(),
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

    protected function createCategoryImage(Category $category, string $sourcePath, string $type): void
    {
        if (!File::exists($sourcePath)) {
            return;
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $extension;
        $path = "category-images/{$category->id}/{$filename}";
        
        Storage::disk('public')->makeDirectory("category-images/{$category->id}");
        Storage::disk('public')->put($path, File::get($sourcePath));
        
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
