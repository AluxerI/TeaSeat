<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use App\Traits\ClearsModelCache;
use App\Traits\HasImage;  // ← Добавляем

class ProductImage extends Model
{
    use HasFactory, ClearsModelCache, HasImage;  // ← Добавляем HasImage

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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::saved(function ($image) {
            $image->product?->clearCache();
        });

        static::deleted(function ($image) {
            $image->product?->clearCache();
            Storage::disk($image->disk)->delete($image->path);
        });
    }
}