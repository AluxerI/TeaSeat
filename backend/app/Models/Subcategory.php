<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\HasIcon;
use App\Traits\ClearsModelCache;
use App\Traits\ResetsAdminBadges;

class Subcategory extends Model
{
    use HasFactory, HasIcon, ClearsModelCache, ResetsAdminBadges;

    protected $fillable = ['category_id', 'name', 'icon', 'slug'];

    protected $hidden = [
        'laravel_through_key',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sub_subcategories()
    {
        return $this->hasMany(Sub_Subcategory::class);
    }

    /**
     * Получить количество товаров
     */
    public function getProductsCount(): int
    {
        $key = "subcategory.{$this->id}.products_count";
        
        return Cache::remember($key, 3600, function () {
            return DB::table('products')
                ->join('sub_subcategory_products', 'products.id', '=', 'sub_subcategory_products.product_id')
                ->join('sub_subcategories', 'sub_subcategory_products.sub_subcategory_id', '=', 'sub_subcategories.id')
                ->where('sub_subcategories.subcategory_id', $this->id)
                ->distinct('products.id')
                ->count('products.id');
        });
    }

    /**
     * Получить количество под-подкатегорий
     */
    public function getSubSubcategoriesCount(): int
    {
        $key = "subcategory.{$this->id}.sub_subcategories_count";
        
        return Cache::remember($key, 3600, function () {
            return $this->sub_subcategories()->count();
        });
    }

    /**
     * Все данные одним ключом
     */
    public function getAllData(): array
    {
        $key = "subcategory.{$this->id}.all";
        
        return Cache::remember($key, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'slug' => $this->slug,
                'icon_url' => $this->icon_url,
                'category_id' => $this->category_id,
                'category_name' => $this->category?->name,
                'sub_subcategories_count' => $this->getSubSubcategoriesCount(),
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
        $key = "subcategory.{$this->id}.table";
        
        return Cache::remember($key, 3600, function () {
            $data = $this->getAllData();
            
            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'icon' => $data['icon'],
                'category_name' => $data['category_name'],
                'sub_subcategories_count' => $data['sub_subcategories_count'],
                'products_count' => $data['products_count'],
            ];
        });
    }

    protected function getCacheKeys(): array
    {
        return [
            "subcategory.{$this->id}.all",
            "subcategory.{$this->id}.table",
            "subcategory.{$this->id}.products_count",
            "subcategory.{$this->id}.sub_subcategories_count",
        ];
    }

    protected static function booted()
    {
        static::saved(function ($subcategory) {
            $subcategory->clearCache();
            $subcategory->category?->clearCache(); // Очищаем кеш родительской категории
        });

        static::deleted(function ($subcategory) {
            $subcategory->clearCache();
            $subcategory->category?->clearCache();
        });
    }
}