<?php
// app/Models/ProductImage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'disk',
        'sort_order',
        'is_main',
        'is_background',
        'alt',
        'title',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_background' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Связь с товаром
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
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
     * Удаление файла при удалении модели
     */
    protected static function booted()
    {
        static::deleting(function ($image) {
            Storage::disk($image->disk)->delete($image->path);
        });
    }
}