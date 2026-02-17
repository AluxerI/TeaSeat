<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    // public $timestamps = false;
    protected $table = 'products';
    protected $guarded = false;
    //     // что-то для оптимизации -_-
    // protected $with = ['subSubcategory.subcategory.category', 'brand'];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }
    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'product_id', 'id');
    }

    public function sub_subcategories()
    {
        return $this->belongsToMany(Sub_subcategory::class, 'sub_subcategory_products');
    }

    // Удобный метод для доступа к первой под-подкатегории
    public function getMainSubSubcategoryAttribute()
    {
        return $this->sub_subcategories->first();
    }
    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'product_promotions');
    }
    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_products');
    }
     public function scopeActive($query)
    {
        return $query->where('is_available', true);
    }
     /**
     * Связь с поставщиками
     */
        public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->withPivot(['cost_price', 'lead_time_days', 'min_order_quantity', 'is_active'])
            ->where('product_supplier.is_active', true) // ← ЯВНО указываем таблицу pivot
            ->where('suppliers.is_active', true) // ← ЯВНО указываем таблицу suppliers
            ->withTimestamps();
    }

    /**
     * Активные поставщики (alias для удобства)
     */
    public function activeSuppliers()
    {
        return $this->suppliers();
    }

    /**
     * Scope для товаров в наличии (на складах)
     */
    public function scopeInStock($query)
    {
        return $query->whereHas('inventories', function($query) {
            $query->where('quantity', '>', 0);
        });
    }

    /**
     * Scope для товаров доступных у поставщиков
     */
    public function scopeAvailableFromSuppliers($query)
    {
        return $query->whereHas('suppliers', function($query) {
            $query->where('product_supplier.is_active', true)
                  ->where('suppliers.is_active', true);
        });
    }

    /**
     * Scope для товаров доступных в городе (склады + поставщики)
     */
    public function scopeAvailableInCity($query, string $city)
    {
        return $query->where(function($q) use ($city) {
            // Товары на складах в городе
            $q->whereHas('inventories.warehouse', function($query) use ($city) {
                $query->where('city', $city)
                      ->where('quantity', '>', 0);
            })
            // ИЛИ товары у поставщиков
            ->orWhereHas('suppliers', function($query) {
                $query->where('product_supplier.is_active', true)
                      ->where('suppliers.is_active', true);
            });
        });
    }

     /**
     * Связь с изображениями
     */
   public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
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
     * Получить все изображения галереи
     */
    public function galleryImages()
    {
        return $this->images()
            ->where('is_main', false)
            ->where('is_background', false)
            ->orderBy('sort_order');
    }

    /**
     * Получить URL главного изображения
     */
    public function getMainImageUrlAttribute(): string
    {
        $mainImage = $this->mainImage();
        return $mainImage ? $mainImage->url : '/images/default-product.jpg';
    }

    /**
     * Получить URL фонового изображения
     */
    public function getBackgroundImageUrlAttribute(): string
    {
        $backgroundImage = $this->backgroundImage();
        return $backgroundImage ? $backgroundImage->url : '/images/default-background.jpg';
    }

    /**
     * Получить все URL изображений для галереи
     */
    public function getGalleryUrlsAttribute(): array
    {
        return $this->galleryImages->map(fn($img) => [
            'url' => $img->url,
            'alt' => $img->alt,
            'title' => $img->title,
            'sort_order' => $img->sort_order,
        ])->values()->toArray();
    }

    /**
     * Получить структурированные данные всех изображений
     */
    public function getImagesDataAttribute(): array
    {
        return [
            'main' => $this->main_image_url,
            'background' => $this->background_image_url,
            'gallery' => $this->gallery_urls,
            'all' => $this->images->map(fn($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'is_main' => $img->is_main,
                'is_background' => $img->is_background,
                'alt' => $img->alt,
                'title' => $img->title,
                'sort_order' => $img->sort_order,
            ]),
        ];
    }
}

