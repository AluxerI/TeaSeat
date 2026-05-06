<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Traits\ClearsModelCache;
use App\Traits\HasDiscountRelations;

class Discount extends Model
{
    use HasFactory, SoftDeletes, ClearsModelCache, HasDiscountRelations;

    protected $fillable = [
        'name',
        'description',
        'value',
        'type',
        'start_date',
        'end_date',
        'is_active',
        'code',
        'is_global',
        'min_order_amount',
        'usage_limit',
        'used_count',
        'usage_per_user',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'usage_per_user' => 'integer',
    ];

    // Типы скидок
    const TYPE_PERSONAL = 'personal';
    const TYPE_FIRST_ORDER = 'first_order';
    const TYPE_LOYALTY = 'loyalty';
    const TYPE_REFERRAL = 'referral';
    const TYPE_PROMOTION = 'promotion';
    const TYPE_CART = 'cart';
    const TYPE_SHIPPING = 'shipping';

    /**
     * Заказы, в которых применена эта скидка
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Кто создал скидку (для админки)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Проверить, можно ли применить скидку к заказу
     */
    public function canApplyToOrder(Order $order): bool
    {
        if (!$this->isValid($order->user)) {
            return false;
        }

        if ($this->min_order_amount && $order->products_total < $this->min_order_amount) {
            return false;
        }

        return true;
    }

    /**
     * Проверить, можно ли применить скидку к корзине
     */
    public function canApplyToCart(float $cartTotal): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->type !== self::TYPE_CART && $this->type !== self::TYPE_SHIPPING) {
            return false;
        }

        if ($this->min_order_amount && $cartTotal < $this->min_order_amount) {
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
        
        // Увеличиваем общий счётчик использований
        $this->increment('used_count');
    }

    /**
     * Применить скидку к заказу (увеличить счётчики)
     */
    public function applyToOrder(Order $order): void
    {
        $this->increment('used_count');
        
        if ($order->user && $this->users()->where('user_id', $order->user->id)->exists()) {
            $this->markAsUsedForUser($order->user);
        }
    }

    /**
     * Получить сумму скидки для заданной суммы
     */
    public function calculateDiscountAmount(float $amount): float
    {
        return round($amount * ($this->value / 100), 2);
    }

    /**
     * Получить название типа скидки на русском
     */
    public function getTypeNameAttribute(): string
    {
        $types = [
            self::TYPE_PERSONAL => 'Персональная',
            self::TYPE_FIRST_ORDER => 'Первый заказ',
            self::TYPE_LOYALTY => 'Лояльность',
            self::TYPE_REFERRAL => 'Реферальная',
            self::TYPE_PROMOTION => 'Акция',
            self::TYPE_CART => 'Скидка на корзину',
            self::TYPE_SHIPPING => 'Скидка на доставку',
        ];
        
        return $types[$this->type] ?? $this->type;
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    public function scopeProductSpecific($query)
    {
        return $query->where('is_global', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithCode($query, string $code)
    {
        return $query->where('code', $code);
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