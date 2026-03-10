<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ClearsModelCache;

class CategoryImage extends Model
{
    use ClearsModelCache;

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

    protected static function booted()
    {
        static::saved(function ($image) {
            $image->category?->clearCache();
        });

        static::deleted(function ($image) {
            $image->category?->clearCache();
        });
    }
}