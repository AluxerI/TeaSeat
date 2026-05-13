<?php

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

    public function getUrlAttribute(): string
    {
        return $this->getUrl();
    }

    public function getUrl(): string
    {
        $disk = Storage::disk($this->disk);
        try {
            return $disk->url($this->path);
        } catch (\Exception $e) {
            return '/storage/' . $this->path;
        }
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->disk === 'public') {
            return asset('storage/' . $this->path);
        }
        $disk = Storage::disk($this->disk);
        return $disk->url($this->path);
    }

    public function setIsMainAttribute($value)
    {
        if ($value) {
            static::where('category_id', $this->category_id)
                ->where('id', '!=', $this->id)
                ->update(['is_main' => false]);
        }
        $this->attributes['is_main'] = $value;
    }

    public function setIsBackgroundAttribute($value)
    {
        if ($value) {
            static::where('category_id', $this->category_id)
                ->where('id', '!=', $this->id)
                ->update(['is_background' => false]);
        }
        $this->attributes['is_background'] = $value;
    }

    public function getCategoryImageDirectory(): string
    {
        return "categories/{$this->category_id}/images";
    }

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
            $directory = dirname($image->path);
            if (Storage::disk('public')->exists($directory) &&
                count(Storage::disk('public')->files($directory)) === 0) {
                Storage::disk('public')->deleteDirectory($directory);
            }
        });
    }
}
