<?php
// app/Traits/HasIcon.php

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
        $type = $this->getIconFolderType();
        $path = "category-icons/{$type}/{$this->id}";
        
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        
        $filePath = $file->storeAs($path, $filename, $disk);
        
        $this->icon = $filePath;
        $this->save();
        
        return $filePath;
    }

    /**
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
     * Определяет тип папки для иконок
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
     * Копирует иконку из временной директории
     */
    public function copyIconFromTemp(string $tempPath, string $disk = 'public'): ?string
    {
        if (!Storage::disk('public')->exists($tempPath)) {
            return null;
        }

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
    }
}