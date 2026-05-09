<?php
<<<<<<< HEAD
// app/Traits/HasIcon.php
=======
>>>>>>> main

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait HasIcon
{
    /**
     * Загружает иконку для модели
     */
    public function uploadIcon(UploadedFile $file, string $disk = 'public'): string
    {
<<<<<<< HEAD
        $type = $this->getIconFolderType();
        $path = "category-icons/{$type}/{$this->id}";
        
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        
=======
        $path = $this->getIconDirectory();
        
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
>>>>>>> main
        $filePath = $file->storeAs($path, $filename, $disk);
        
        $this->icon = $filePath;
        $this->save();
        
        return $filePath;
    }

    /**
<<<<<<< HEAD
=======
     * Получить директорию для иконки
     */
    public function getIconDirectory(): string
    {
        $type = $this->getIconFolderType();
        $id = $this->id ?? 'temp';
        
        return "category-icons/{$type}/{$id}";
    }

    /**
     * Переместить иконку из временной папки в папку модели
     */
    public function moveIconToFolder(?string $tempPath = null): ?string
    {
        $path = $tempPath ?? $this->icon;
        
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        // Если иконка уже в правильной папке, ничего не делаем
        $expectedPath = $this->getIconDirectory() . '/' . basename($path);
        if ($path === $expectedPath) {
            return $path;
        }

        // Создаем папку, если её нет
        Storage::disk('public')->makeDirectory($this->getIconDirectory());
        
        // Перемещаем файл
        Storage::disk('public')->move($path, $expectedPath);
        
        $this->icon = $expectedPath;
        $this->saveQuietly();
        
        return $expectedPath;
    }

    /**
>>>>>>> main
     * Удаляет иконку модели
     */
    public function deleteIcon(): bool
    {
        if ($this->icon && Storage::disk('public')->exists($this->icon)) {
            Storage::disk('public')->delete($this->icon);
            $this->icon = null;
            $this->save();
            return true;
        }
        
        return false;
    }

    /**
<<<<<<< HEAD
     * Определяет тип папки для иконок
=======
     * Удаляет всю папку с иконками модели
     */
    public function deleteIconDirectory(): bool
    {
        $directory = dirname($this->getIconDirectory());
        
        if (Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->deleteDirectory($directory);
            return true;
        }
        
        return false;
    }

    /**
     * Получить URL иконки
     */
    public function getIconUrlAttribute(): ?string
    {
        if (!$this->icon) {
            return null;
        }
        
        return asset('storage/' . $this->icon);
    }

    /**
     * Определяет тип папки для иконок в зависимости от класса модели
>>>>>>> main
     */
    protected function getIconFolderType(): string
    {
        $className = class_basename($this);
        
        return match($className) {
            'Category' => 'categories',
            'Subcategory' => 'subcategories',
            'Sub_Subcategory' => 'sub-subcategories',
            default => strtolower($className)
        };
    }

    /**
<<<<<<< HEAD
     * Копирует иконку из временной директории
=======
     * Копирует иконку из временной директории (для обратной совместимости)
>>>>>>> main
     */
    public function copyIconFromTemp(string $tempPath, string $disk = 'public'): ?string
    {
        if (!Storage::disk('public')->exists($tempPath)) {
            return null;
        }

<<<<<<< HEAD
        $type = $this->getIconFolderType();
        $path = "category-icons/{$type}/{$this->id}";
        
        $filename = basename($tempPath);
        
        Storage::disk('public')->makeDirectory($path);
        Storage::disk('public')->copy($tempPath, $path . '/' . $filename);
        
        $this->icon = $path . '/' . $filename;
        $this->save();
        
        return $this->icon;
    }

    public function getIconUrlAttribute(): ?string
    {
        if (!$this->icon) {
            return null;
        }
        
        return $this->getIconUrl();
    }

    public function getIconUrl(): ?string
    {
        if (!$this->icon) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        
        try {
            return $disk->url($this->icon);
        } catch (\Exception $e) {
            // Запасной вариант
            return '/storage/' . $this->icon;
        }
    }


    public function getImageIconUrlAttribute(): ?string
    {
        if (!$this->icon) {
            return null;
        }

        // Для public диска используем asset() как в ProductImage
        return asset('storage/' . $this->icon);
=======
        $this->icon = $tempPath;
        $this->save();
        
        return $this->moveIconToFolder();
>>>>>>> main
    }
}