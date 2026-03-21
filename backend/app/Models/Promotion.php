<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use App\Traits\HasPromotionRelations;

class Promotion extends Model
{
    use HasFactory, SoftDeletes, HasPromotionRelations;

    protected $fillable = [
        'name',
        'description',
        'discount_percent',
        'is_active',
        'start_date',
        'end_date',
        'code',
        'type',
        'min_order_amount',
        'usage_limit',
        'used_count',
        'usage_per_user',
        'is_public'
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'usage_per_user' => 'integer',
    ];

    const TYPE_PRODUCT = 'product';
    const TYPE_CART = 'cart';
    const TYPE_SHIPPING = 'shipping';

    /**
     * Получить данные акции для отображения
     */
    public function getAllData(): array
    {
        $key = "promotion.{$this->id}.all";
        
        return Cache::remember($key, 3600, function () {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'code' => $this->code,
                'type' => $this->type,
                'discount_percent' => (float) $this->discount_percent,
                'is_active' => $this->is_active,
                'products_count' => $this->products()->count(),
                'used_count' => $this->used_count,
                'start_date' => $this->start_date?->format('d.m.Y'),
                'end_date' => $this->end_date?->format('d.m.Y'),
            ];
        });
    }

    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return ["promotion.{$this->id}.all"];
    }

    /**
     * События модели
     */
    protected static function booted()
    {
        static::saved(function ($promotion) {
            Cache::forget("promotion.{$promotion->id}.all");
        });

        static::deleted(function ($promotion) {
            Cache::forget("promotion.{$promotion->id}.all");
        });
    }

    // 👇 ЭТОТ МЕТОД ОСТАВЛЯЕМ (он использует старую таблицу product_promotions)
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_promotions');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // 👇 УДАЛЯЕМ ЭТИ МЕТОДЫ - они конфликтуют с полиморфными из трейта
    // public function categories()
    // {
    //     return $this->belongsToMany(Category::class, 'promotion_categories', 'promotion_id', 'category_id');
    // }
    
    // public function subcategories()
    // {
    //     return $this->belongsToMany(Subcategory::class, 'promotion_subcategories', 'promotion_id', 'subcategory_id');
    // }
    
    // public function subSubcategories()
    // {
    //     return $this->belongsToMany(Sub_Subcategory::class, 'promotion_sub_subcategories', 'promotion_id', 'sub_subcategory_id');
    // }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }
}