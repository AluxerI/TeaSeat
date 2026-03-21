<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ClearsModelCache;
use App\Traits\HasImage;
use Illuminate\Support\Facades\Storage;

class CategoryImage extends Model
{
    use ClearsModelCache, HasImage;

    protected $fillable = [
        'category_id',
        'path',
        'disk',
        'is_main',
        'is_background',
        'alt',
        'title',
        'sort_order',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_background' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
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
        });
    }
}