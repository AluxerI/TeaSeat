<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Traits\ClearsModelCache;
use App\Traits\ResetsAdminBadges;
use App\Traits\HasIcon;

class Category extends Model
{
    use HasFactory, HasIcon, ClearsModelCache, ResetsAdminBadges;

    protected $fillable = [
        'name', 
        'icon',
        'slug',
        'promo_title',
        'promo_subtitle',
        'promo_description',
        'promo_button_text',
        'promo_button_link',
        'promo_settings',
    ];

    protected $casts = [
        'promo_settings' => 'array',
    ];

    protected $hidden = [
        'laravel_through_key',
    ];

    /**
     * Отношения
     */
    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    public function images()
    {
        return $this->hasMany(CategoryImage::class);
    }

    /**
     * Получить главное изображение (путь)
     */
    public function getMainImage(): ?string
    {
        $key = "category.{$this->id}.main_image";
        
        return Cache::remember($key, 3600, function () {
            return $this->images()
                ->where('is_main', true)
                ->value('path');
        });
    }

    public function getMainImageUrl(): ?string
    {
        $path = $this->getMainImage();
        if (!$path) return null;

        // Для публичного диска используем asset
        return asset('storage/' . $path);
    }



    /**
     * Получить фоновое изображение (путь)
     */
    public function getBackgroundImage(): ?string
    {
        $key = "category.{$this->id}.background_image";
        
        return Cache::remember($key, 3600, function () {
            return $this->images()
                ->where('is_background', true)
                ->value('path');
        });
    }

    /**
     * Получить URL фонового изображения
     */
    public function getBackgroundImageUrl(): ?string
    {
        $path = $this->getBackgroundImage();
        if (!$path) return null;
        
        return asset('storage/' . $path);
    }
    /**
     * Получить количество подкатегорий
     */
    public function getSubcategoriesCount(): int
    {
        $key = "category.{$this->id}.subcategories_count";
        
        return Cache::remember($key, 3600, function () {
            return $this->subcategories()->count();
        });
    }

    /**
     * Получить количество товаров в категории
     */
    public function getProductsCount(): int
    {
        $key = "category.{$this->id}.products_count";
        
        return Cache::remember($key, 3600, function () {
            return DB::table('products')
                ->join('sub_subcategory_products', 'products.id', '=', 'sub_subcategory_products.product_id')
                ->join('sub_subcategories', 'sub_subcategory_products.sub_subcategory_id', '=', 'sub_subcategories.id')
                ->join('subcategories', 'sub_subcategories.subcategory_id', '=', 'subcategories.id')
                ->where('subcategories.category_id', $this->id)
                ->distinct('products.id')
                ->count('products.id');
        });
    }

    /**
     * ВСЕ ДАННЫЕ ОДНИМ КЛЮЧОМ
     */
    public function getAllData(): array
    {
        $key = "category.{$this->id}.all";
        
        return Cache::remember($key, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'slug' => $this->slug,
                'icon' => $this->icon,
                'icon_url' => $this->icon_url, // из трейта HasIcon
                'main_image' => $this->getMainImage(),
                'main_image_url' => $this->getMainImageUrl(),
                'background_image' => $this->getBackgroundImage(),
                'background_image_url' => $this->getBackgroundImageUrl(), // ← ДОБАВИТЬ
                'subcategories_count' => $this->getSubcategoriesCount(),
                'products_count' => $this->getProductsCount(),
                'created_at' => $this->created_at?->format('d.m.Y'),
            ];
        });
    }

    /**
     * Данные для таблицы
     */
    public function getTableRow(): array
    {
        $key = "category.{$this->id}.table";
        
        return Cache::remember($key, 3600, function () {
            $data = $this->getAllData();
            
            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'icon_url' => $data['icon_url'],
                'main_image_url' => $data['main_image_url'],
                'subcategories_count' => $data['subcategories_count'],
                'products_count' => $data['products_count'],
            ];
        });
    }

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "category.{$this->id}.all",
            "category.{$this->id}.table",
            "category.{$this->id}.main_image",
            "category.{$this->id}.background_image",
            "category.{$this->id}.products_count",
            "category.{$this->id}.subcategories_count",
        ];
    }

    /**
     * События модели
     */
    protected static function booted()
    {
        static::saved(function ($category) {
            $category->clearCache();
        });

        static::deleted(function ($category) {
            $category->clearCache();
        });
    }
}