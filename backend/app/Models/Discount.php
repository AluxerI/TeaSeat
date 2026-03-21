<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Traits\ClearsModelCache;
use App\Traits\HasDiscountRelations;

class Discount extends Model
{
    use HasFactory, ClearsModelCache, HasDiscountRelations;

    protected $fillable = [
        'name',
        'value', 
        'is_active',
        'is_global', 
        'start_at',
        'end_at',
        'type',
        'min_order_amount',
        'usage_limit'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'usage_limit' => 'integer',
    ];

    const TYPE_PERSONAL = 'personal';
    const TYPE_FIRST_ORDER = 'first_order';
    const TYPE_LOYALTY = 'loyalty';
    const TYPE_REFERRAL = 'referral';

    // 👇 ЭТИ МЕТОДЫ УЖЕ ЕСТЬ В ТРЕЙТЕ, НО ОНИ ПЕРЕОПРЕДЕЛЕНЫ ЗДЕСЬ
    // Нужно их удалить или закомментировать

    // public function products()
    // {
    //     return $this->belongsToMany(Product::class, 'discount_products', 'discount_id', 'product_id')
    //         ->withPivot(['is_used', 'activated_at'])
    //         ->withTimestamps();
    // }

    // public function categories()
    // {
    //     return $this->belongsToMany(Category::class, 'discount_categories', 'discount_id', 'category_id')
    //         ->withTimestamps();
    // }

    // public function subcategories()
    // {
    //     return $this->belongsToMany(Subcategory::class, 'discount_subcategories', 'discount_id', 'subcategory_id')
    //         ->withTimestamps();
    // }

    // public function subSubcategories()
    // {
    //     return $this->belongsToMany(Sub_Subcategory::class, 'discount_sub_subcategories', 'discount_id', 'sub_subcategory_id')
    //         ->withTimestamps();
    // }

    // public function users()
    // {
    //     return $this->belongsToMany(User::class, 'discount_users', 'discount_id', 'user_id')
    //         ->withPivot(['is_used', 'used_count', 'activated_at'])
    //         ->withTimestamps();
    // }

    /**
     * Проверить, применяется ли скидка к товару
     */
    public function appliesToProduct(Product $product): bool
    {
        // Глобальные скидки применяются ко всем товарам
        if ($this->is_global) {
            return true;
        }
        
        // Для не-глобальных проверяем связь с товаром через полиморфную связь
        return $this->products()->where('product_id', $product->id)->exists();
    }

    /**
     * Scope для глобальных скидок
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    /**
     * Scope для не-глобальных (товарных) скидок
     */
    public function scopeProductSpecific($query)
    {
        return $query->where('is_global', false);
    }
    
    /**
     * Заказы, в которых применена эта скидка
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Проверить, действительна ли скидка
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->start_at && $this->start_at->isFuture()) {
            return false;
        }

        if ($this->end_at && $this->end_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Проверить, можно ли применить скидку к заказу
     */
    public function canApplyToOrder(Order $order): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->min_order_amount && $order->products_total < $this->min_order_amount) {
            return false;
        }

        return true;
    }

    /**
     * Получить скидку для конкретного пользователя
     */
    public function forUser(User $user)
    {
        return $this->users()->where('user_id', $user->id)->first();
    }

    /**
     * Пометить скидку как использованную для пользователя
     */
    public function markAsUsedForUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'is_used' => true,
            'used_count' => DB::raw('used_count + 1')
        ]);
    }

    /**
     * Scope для активных скидок
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('start_at')
                  ->orWhere('start_at', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('end_at')
                  ->orWhere('end_at', '>=', now());
            });
    }

    /**
     * Scope для скидок определенного типа
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
    
    /**
     * Ключи кеша для очистки
     */
    protected function getCacheKeys(): array
    {
        return [
            "discount.{$this->id}",
            "discount.{$this->id}.users",
            "discount.{$this->id}.products",
            "discount.{$this->id}.categories",
            "discount.{$this->id}.subcategories",
            "discount.{$this->id}.subSubcategories",
        ];
    }
}