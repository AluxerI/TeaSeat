<?php
<<<<<<< HEAD
// app/Models/CategoryImage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
=======

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ClearsModelCache;
use App\Traits\HasImage;
>>>>>>> main
use Illuminate\Support\Facades\Storage;

class CategoryImage extends Model
{
<<<<<<< HEAD
    use HasFactory;
=======
    use ClearsModelCache, HasImage;
>>>>>>> main

    protected $fillable = [
        'category_id',
        'path',
        'disk',
        'is_main',
        'is_background',
        'alt',
        'title',
<<<<<<< HEAD
=======
        'sort_order',
>>>>>>> main
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_background' => 'boolean',
<<<<<<< HEAD
    ];

    /**
     * Связь с категорией
     */
=======
        'sort_order' => 'integer',
    ];

>>>>>>> main
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
<<<<<<< HEAD
     * Получить полный URL изображения
     */
    public function getUrlAttribute(): string
    {
        return $this->getUrl();
    }

    /**
     * Метод для получения URL
     */
    public function getUrl(): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);
        
        try {
            // @phpstan-ignore-next-line
            return $disk->url($this->path);
        } catch (\Exception $e) {
            return '/storage/' . $this->path;
        }
    }

    /**
     * Получить путь для вставки в img src
     */
    public function getImageUrlAttribute(): string
    {
        if ($this->disk === 'public') {
            return asset('storage/' . $this->path);
        }
        
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);
        
        // @phpstan-ignore-next-line
        return $disk->url($this->path);
    }

    /**
     * Мутаторы для автоматической уникальности флагов
     */
    public function setIsMainAttribute($value)
    {
        if ($value) {
            // Снимаем флаг is_main с других изображений этой категории
            static::where('category_id', $this->category_id)
                ->where('id', '!=', $this->id)
                ->update(['is_main' => false]);
        }
        $this->attributes['is_main'] = $value;
    }

    public function setIsBackgroundAttribute($value)
    {
        if ($value) {
            // Снимаем флаг is_background с других изображений этой категории
            static::where('category_id', $this->category_id)
                ->where('id', '!=', $this->id)
                ->update(['is_background' => false]);
        }
        $this->attributes['is_background'] = $value;
    }

    /**
     * Удаление файла при удалении модели
     */
    protected static function booted()
    {
        static::deleting(function ($image) {
            Storage::disk($image->disk)->delete($image->path);
=======
     * Получить директорию для изображений категории
     */
    public function getCategoryImageDirectory(): string
    {
        return "categories/{$this->category_id}/images";
    }

    /**
     * Переместить изображение в правильную папку
     */
    public function moveToCategoryFolder(): void
    {
        if (!$this->path || !Storage::disk('public')->exists($this->path)) {
            return;
        }

        $expectedPath = $this->getCategoryImageDirectory() . '/' . basename($this->path);
        
        if ($this->path === $expectedPath) {
            return;
        }

        Storage::disk('public')->makeDirectory($this->getCategoryImageDirectory());
        Storage::disk('public')->move($this->path, $expectedPath);
        
        $this->updateQuietly(['path' => $expectedPath]);
    }

    protected static function booted()
    {
        static::saved(function ($image) {
            $image->moveToCategoryFolder();
            $image->category?->clearCache();
        });

        static::deleted(function ($image) {
            $image->category?->clearCache();
            Storage::disk($image->disk)->delete($image->path);
            
            // Если папка категории пуста, удаляем её
            $directory = dirname($image->path);
            if (Storage::disk('public')->exists($directory) && 
                count(Storage::disk('public')->files($directory)) === 0) {
                Storage::disk('public')->deleteDirectory($directory);
            }
>>>>>>> main
        });
    }
}