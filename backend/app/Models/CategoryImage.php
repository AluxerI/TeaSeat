<?php
// app/Models/CategoryImage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CategoryImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'path',
        'disk',
        'is_main',
        'is_background',
        'alt',
        'title',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_background' => 'boolean',
    ];


    protected $hidden = [
        'laravel_through_key',
        // другие служебные поля
    ];    /**
     * Связь с категорией
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
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
        });
    }
}