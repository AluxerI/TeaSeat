<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\HasIcon;
use App\Traits\ClearsModelCache;
use App\Traits\ResetsAdminBadges;

class Sub_Subcategory extends Model
{
    use HasFactory, HasIcon, ClearsModelCache, ResetsAdminBadges;

    protected $table = 'sub_subcategories';

    protected $fillable = ['subcategory_id', 'name', 'icon', 'slug'];

    protected $hidden = [
        'laravel_through_key',
    ];

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'sub_subcategory_products', 
            'sub_subcategory_id',    
            'product_id'          
        );
    }

    /**
     * Получить количество товаров
     */
    public function getProductsCount(): int
    {
        $key = "sub_subcategory.{$this->id}.products_count";
        
        return Cache::remember($key, 3600, function () {
            return $this->products()->count();
        });
    }

    /**
     * Получить полный путь категории
     */
    public function getFullPath(): array
    {
        $key = "sub_subcategory.{$this->id}.path";
        
        return Cache::remember($key, 3600, function () {
            $subcategory = $this->subcategory()->with('category')->first();
            
            return [
                'category_id' => $subcategory?->category?->id,
                'category_name' => $subcategory?->category?->name,
                'subcategory_id' => $subcategory?->id,
                'subcategory_name' => $subcategory?->name,
                'sub_subcategory_id' => $this->id,
                'sub_subcategory_name' => $this->name,
            ];
        });
    }

    /**
     * Все данные одним ключом
     */
    public function getAllData(): array
    {
        $key = "sub_subcategory.{$this->id}.all";
        
        return Cache::remember($key, 3600, function () {
            $path = $this->getFullPath();
            
            return [
                'id' => $this->id,
                'name' => $this->name,
                'slug' => $this->slug,
                'icon_url' => $this->icon_url,
                'subcategory_id' => $this->subcategory_id,
                'subcategory_name' => $path['subcategory_name'],
                'category_name' => $path['category_name'],
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
        $key = "sub_subcategory.{$this->id}.table";
        
        return Cache::remember($key, 3600, function () {
            $data = $this->getAllData();
            
            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'icon' => $data['icon'],
                'subcategory_name' => $data['subcategory_name'],
                'category_name' => $data['category_name'],
                'products_count' => $data['products_count'],
            ];
        });
    }

    protected function getCacheKeys(): array
    {
        return [
            "sub_subcategory.{$this->id}.all",
            "sub_subcategory.{$this->id}.table",
            "sub_subcategory.{$this->id}.path",
            "sub_subcategory.{$this->id}.products_count",
        ];
    }

    protected static function booted()
    {
        static::saved(function ($subSubcategory) {
            $subSubcategory->moveIconToFolder();
            $subSubcategory->clearCache();
        });
    
        static::deleted(function ($subSubcategory) {
            $subSubcategory->deleteIconDirectory();
            $subSubcategory->clearCache();
        });
    }
}