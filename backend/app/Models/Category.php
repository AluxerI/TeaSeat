<?php
// app/Models/Category.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasIcon;

class Category extends Model
{
    use HasFactory, HasIcon;

    protected $fillable = ['name', 'icon'];

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

        protected $hidden = [
        'laravel_through_key',
        // другие служебные поля
    ];
    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            Sub_Subcategory::class,
            'subcategory_id',
            'sub_subcategory_id',
            'id',
            'id'
        );
    }

    /**
     * Связь с изображениями категории
     */
    public function images()
    {
        return $this->hasMany(CategoryImage::class);
    }

    /**
     * Получить главное изображение
     */
    public function mainImage()
    {
        return $this->images()->where('is_main', true)->first();
    }

    /**
     * Получить фоновое изображение
     */
    public function backgroundImage()
    {
        return $this->images()->where('is_background', true)->first();
    }

    /**
     * Получить URL главного изображения
     */
    public function getMainImageUrlAttribute(): ?string
    {
        $mainImage = $this->mainImage();
        return $mainImage ? $mainImage->url : null;
    }

    /**
     * Получить URL фонового изображения
     */
    public function getBackgroundImageUrlAttribute(): ?string
    {
        $backgroundImage = $this->backgroundImage();
        return $backgroundImage ? $backgroundImage->url : null;
    }

    /**
     * Получить структурированные данные изображений (только main и background)
     */
    public function getImagesDataAttribute(): array
    {
        return [
            'main' => $this->main_image_url,
            'background' => $this->background_image_url,
        ];
    }
}