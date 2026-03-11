<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait HasImage
{
    /**
     * Получить URL изображения (для совместимости с фронтендом)
     */
    public function getUrlAttribute(): ?string
    {
        return $this->getImageUrl();
    }

    /**
     * Получить URL изображения
     */
    public function getImageUrl(): ?string
    {
        if (!$this->path) {
            return null;
        }

        $disk = $this->disk ?? 'public';
        
        // Для публичного диска используем asset (это не вызывает ошибку VS Code)
        if ($disk === 'public') {
            return asset('storage/' . $this->path);
        }
        
        // Для других дисков используем Storage facade
        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);
        return $storage->url($this->path);
    }

    /**
     * Получить путь для вставки в img src (алиас для getImageUrl)
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->getImageUrl();
    }
}