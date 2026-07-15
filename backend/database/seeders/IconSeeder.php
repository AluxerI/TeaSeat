<?php
// database/seeders/IconSeeder.php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class IconSeeder extends Seeder
{
    /**
     * Путь к исходным иконкам сидера относительно database/.
     */
    protected string $sourceIconsPath = 'seeders/assets/category-icons';
    
    /**
     * Массив доступных иконок
     */
    protected array $availableIcons = [];

    public function run(): void
    {
        // Очищаем старые иконки
        $this->cleanOldIcons();
        
        // Загружаем список доступных иконок
        $this->loadAvailableIcons();
        
        if (empty($this->availableIcons)) {
            $this->command->warn('Нет иконок в папке database/seeders/assets/category-icons/');
            return;
        }

        // Распределяем иконки по категориям
        $this->assignIconsToCategories();
        
        // Распределяем иконки по подкатегориям
        $this->assignIconsToSubcategories();
        
        // Распределяем иконки по под-подкатегориям
        $this->assignIconsToSubSubcategories();
        
        $this->command->info('Иконки успешно распределены!');
    }

    /**
     * Очищает папку с иконками
     */
    protected function cleanOldIcons(): void
    {
        Storage::disk('public')->deleteDirectory('category-icons');
        Storage::disk('public')->makeDirectory('category-icons');
    }

    /**
     * Загружает список доступных иконок из временной папки
     */
    protected function loadAvailableIcons(): void
    {
        $iconsPath = database_path($this->sourceIconsPath);
        
        if (!File::isDirectory($iconsPath)) {
            return;
        }

        $files = File::files($iconsPath);
        
        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            if (in_array($extension, ['png', 'svg', 'jpg', 'jpeg'])) {
                $this->availableIcons[] = $file->getPathname();
            }
        }
    }

    /**
     * Копирует иконку из временной папки в постоянную
     */
    protected function copyIcon(string $sourcePath, string $type, int $id): ?string
    {
        if (!File::exists($sourcePath)) {
            return null;
        }

        $filename = basename($sourcePath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Генерируем уникальное имя
        $newFilename = $type . '_' . $id . '_' . time() . '_' . uniqid() . '.' . $extension;
        $newPath = "category-icons/{$type}/{$id}/{$newFilename}";
        
        // Создаем директорию
        Storage::disk('public')->makeDirectory(dirname($newPath));
        
        // Копируем файл
        Storage::disk('public')->put($newPath, File::get($sourcePath));
        
        return $newPath;
    }

    /**
     * Назначает иконки категориям
     */
    protected function assignIconsToCategories(): void
    {
        $categories = Category::all();
        $iconCount = count($this->availableIcons);
        
        foreach ($categories as $index => $category) {
            if ($iconCount === 0) {
                // Если иконок нет, используем заглушку
                $category->icon = $this->createPlaceholderIcon($category, 'category');
                $category->save();
                continue;
            }
            
            // Циклически выбираем иконку
            $iconIndex = $index % $iconCount;
            $tempPath = $this->availableIcons[$iconIndex];
            
            $newPath = $this->copyIcon($tempPath, 'categories', $category->id);
            
            if ($newPath) {
                $category->icon = $newPath;
                $category->save();
            }
        }
    }

    /**
     * Назначает иконки подкатегориям
     */
    protected function assignIconsToSubcategories(): void
    {
        $subcategories = Subcategory::all();
        $iconCount = count($this->availableIcons);
        
        foreach ($subcategories as $index => $subcategory) {
            if ($iconCount === 0) {
                $subcategory->icon = $this->createPlaceholderIcon($subcategory, 'subcategory');
                $subcategory->save();
                continue;
            }
            
            $iconIndex = $index % $iconCount;
            $tempPath = $this->availableIcons[$iconIndex];
            
            $newPath = $this->copyIcon($tempPath, 'subcategories', $subcategory->id);
            
            if ($newPath) {
                $subcategory->icon = $newPath;
                $subcategory->save();
            }
        }
    }

    /**
     * Назначает иконки под-подкатегориям
     */
    protected function assignIconsToSubSubcategories(): void
    {
        $subSubcategories = Sub_Subcategory::all();
        $iconCount = count($this->availableIcons);
        
        foreach ($subSubcategories as $index => $subSubcategory) {
            if ($iconCount === 0) {
                $subSubcategory->icon = $this->createPlaceholderIcon($subSubcategory, 'subsubcategory');
                $subSubcategory->save();
                continue;
            }
            
            $iconIndex = $index % $iconCount;
            $tempPath = $this->availableIcons[$iconIndex];
            
            $newPath = $this->copyIcon($tempPath, 'sub-subcategories', $subSubcategory->id);
            
            if ($newPath) {
                $subSubcategory->icon = $newPath;
                $subSubcategory->save();
            }
        }
    }

    /**
     * Создает иконку-заглушку
     */
    protected function createPlaceholderIcon($model, string $type): ?string
    {
        // Создаем простую SVG заглушку с первой буквой названия
        $firstLetter = mb_substr($model->name, 0, 1, 'UTF-8');
        
        $svg = <<<SVG
        <svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">
            <rect width="100" height="100" fill="#f0f0f0"/>
            <text x="50" y="50" font-size="40" text-anchor="middle" dy=".3em" fill="#999">{$firstLetter}</text>
        </svg>
        SVG;
        
        $filename = $type . '_' . $model->id . '_placeholder_' . time() . '.svg';
        $path = "category-icons/{$type}s/{$model->id}/{$filename}";
        
        Storage::disk('public')->put($path, $svg);
        
        return $path;
    }
}
