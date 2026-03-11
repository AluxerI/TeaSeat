<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\ClearsModelCache;
use App\Traits\ResetsAdminBadges;

class Product extends Model
{
    use HasFactory, ClearsModelCache, ResetsAdminBadges;

    protected $table = 'products';
    protected $guarded = false;

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'total_quantity' => 'integer',
        'cached_data' => 'array',
    ];

    /**
     * Отношения
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class)->select(['id', 'name']);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->select([
            'id', 'product_id', 'path', 'is_main', 'is_background', 'sort_order'
        ]);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class)->select([
            'product_id', 'warehouse_id', 'quantity'
        ]);
    }

    public function sub_subcategories()
    {
        return $this->belongsToMany(
            Sub_Subcategory::class,
            'sub_subcategory_products',
            'product_id',
            'sub_subcategory_id'
        )->select(['sub_subcategories.id', 'sub_subcategories.name', 'sub_subcategories.subcategory_id']);
    }

    // Тяжелые отношения - используем только где нужно
    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'product_promotions');
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_products');
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->withPivot(['cost_price', 'lead_time_days', 'min_order_quantity', 'is_active'])
            ->where('product_supplier.is_active', true)
            ->where('suppliers.is_active', true)
            ->withTimestamps();
    }

    /**
     * Получить путь к главному изображению
     */
    private function getMainImagePath(): ?string
    {
        $key = "product.{$this->id}.main_image_path";

        return Cache::remember($key, 3600, function () {
            return $this->images()
                ->where('is_main', true)
                ->value('path');
        });
    }

    /**
     * Получить URL главного изображения
     */
    private function getMainImageUrl(): ?string
    {
        $key = "product.{$this->id}.main_image_url";

        return Cache::remember($key, 3600, function () {
            $image = $this->images()
                ->where('is_main', true)
                ->first();

            return $image?->image_url;
        });
    }

    /**
     * Получить фоновое изображение (путь)
     */
    private function getBackgroundImagePath(): ?string
    {
        $key = "product.{$this->id}.background_image_path";

        return Cache::remember($key, 3600, function () {
            return $this->images()
                ->where('is_background', true)
                ->value('path');
        });
    }

    /**
     * Получить URL фонового изображения
     */
    private function getBackgroundImageUrl(): ?string
    {
        $key = "product.{$this->id}.background_image_url";

        return Cache::remember($key, 3600, function () {
            $image = $this->images()
                ->where('is_background', true)
                ->first();

            return $image?->image_url;
        });
    }

    /**
     * Получить данные галереи
     */
    private function getGalleryData(): array
    {
        $key = "product.{$this->id}.gallery_data";

        return Cache::remember($key, 3600, function () {
            return $this->images()
                ->where('is_main', false)
                ->where('is_background', false)
                ->orderBy('sort_order')
                ->get()
                ->map(fn($img) => [
                    'path' => $img->path,
                    'url' => $img->image_url,
                    'alt' => $img->alt,
                    'title' => $img->title,
                    'sort_order' => $img->sort_order,
                ])
                ->values()
                ->toArray();
        });
    }

    /**
     * Получить путь по категориям
     */
    private function getCategoryPath(): ?array
    {
        $key = "product.{$this->id}.category_path";
        
        return Cache::remember($key, 3600, function () {
            $subSubcategory = $this->sub_subcategories()
                ->with(['subcategory.category' => function($q) {
                    $q->select(['id', 'name']);
                }])
                ->first();
            
            if (!$subSubcategory) return null;
            
            return [
                'category' => $subSubcategory->subcategory->category->name ?? null,
                'subcategory' => $subSubcategory->subcategory->name ?? null,
                'sub_subcategory' => $subSubcategory->name,
            ];
        });
    }

    /**
     * Получить общее количество на складах
     */
    private function getTotalQuantity(): int
    {
        $key = "product.{$this->id}.total_quantity";
        
        return Cache::remember($key, 300, function () {
            return (int) $this->inventories()->sum('quantity');
        });
    }

    /**
     * Получить все данные товара одним ключом
     */
    public function getAllData(): array
    {
        $key = "product.{$this->id}.all";

        return Cache::remember($key, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'price' => (float) $this->price,
                'main_image_path' => $this->getMainImagePath(),
                'main_image_url' => $this->getMainImageUrl(),
                'background_image_path' => $this->getBackgroundImagePath(),
                'background_image_url' => $this->getBackgroundImageUrl(),
                'gallery_data' => $this->getGalleryData(),
                'category_path' => $this->getCategoryPath(),
                'total_quantity' => $this->getTotalQuantity(),
                'sold_count' => $this->sold_count,
                'is_available' => $this->is_available,
                'created_at' => $this->created_at?->format('d.m.Y'),
            ];
        });
    }

    /**
     * Данные для таблицы
     */
    public function getTableRow(): array
    {
        $key = "product.{$this->id}.table";
        
        return Cache::remember($key, 3600, function () {
            $data = $this->getAllData();
            
            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'price' => $data['price'],
                'main_image' => $data['main_image_url'],
                'category' => $data['category_path']['category'] ?? '—',
                'total_quantity' => $data['total_quantity'],
                'is_available' => $data['is_available'],
            ];
        });
    }

    /**
     * Детальные данные для карточки товара
     */
    public function getDetailedData(): array
    {
        $key = "product.{$this->id}.detailed";
        
        return Cache::remember($key, 3600, function () {
            return array_merge($this->getAllData(), [
                'description' => $this->description,
                'ingredients' => $this->ingredients,
                'weight_grams' => $this->weight_grams,
                'brand_name' => $this->brand?->name,
            ]);
        });
    }

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "product.{$this->id}.all",
            "product.{$this->id}.table",
            "product.{$this->id}.detailed",
            "product.{$this->id}.main_image_path",
            "product.{$this->id}.main_image_url",
            "product.{$this->id}.background_image_path",
            "product.{$this->id}.background_image_url",
            "product.{$this->id}.gallery_data",
            "product.{$this->id}.category_path",
            "product.{$this->id}.total_quantity",
        ];
    }

    /**
     * Обновление кешированных полей
     */
    public function updateCacheFields(): void
    {
        $totalQuantity = $this->getTotalQuantity();
        
        $this->updateQuietly([
            'total_quantity' => $totalQuantity,
            'is_available' => $totalQuantity > 0,
        ]);
        
        $this->clearCache();
    }

    /**
     * События модели
     */
    protected static function booted()
    {
        static::saved(function ($product) {
            $product->updateCacheFields();
        });

        static::deleted(function ($product) {
            $product->clearCache();
            // Очищаем кеши страниц
            for ($i = 1; $i <= 10; $i++) {
                Cache::forget("products.page.{$i}.ids");
            }
        });
    }

    /**
     * Scopes
     */
    public function scopeInStock($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeAvailableInCity($query, string $city)
    {
        return $query->where(function($q) use ($city) {
            $q->whereHas('inventories.warehouse', function($query) use ($city) {
                $query->where('city', $city)->where('quantity', '>', 0);
            })
            ->orWhereHas('suppliers');
        });
    }
}