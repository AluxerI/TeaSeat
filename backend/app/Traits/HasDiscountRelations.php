<?php

namespace App\Traits;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;

/**
 * @property bool $is_global
 * @property string $type
 * @property int|null $usage_per_user
 * @property bool $is_active
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int|null $usage_limit
 * @property int $used_count
 */
trait HasDiscountRelations
{
    public function categories()
    {
        return $this->morphedByMany(Category::class, 'discountable');
    }

    public function subcategories()
    {
        return $this->morphedByMany(Subcategory::class, 'discountable');
    }

    public function subSubcategories()
    {
        return $this->morphedByMany(Sub_Subcategory::class, 'discountable');
    }

    public function products()
    {
        return $this->morphedByMany(Product::class, 'discountable');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'discount_user')
            ->withPivot(['is_used', 'used_count', 'activated_at'])
            ->withTimestamps();
    }

    /**
     * Проверить, применяется ли скидка к конкретному продукту
     */
    public function appliesToProduct(Product $product): bool
    {
        // Глобальные скидки применяются ко всем
        if ($this->is_global) {
            return true;
        }

        // Прямая связь с продуктом
        if ($this->products()->where('product_id', $product->id)->exists()) {
            return true;
        }

        // Проверяем категории товара
        foreach ($product->sub_subcategories as $subSub) {
            if ($this->subSubcategories()->where('sub_subcategory_id', $subSub->id)->exists()) {
                return true;
            }
            if ($this->subcategories()->where('subcategory_id', $subSub->subcategory_id)->exists()) {
                return true;
            }
            if ($this->categories()->where('category_id', $subSub->subcategory->category_id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверить, доступна ли скидка пользователю
     */
    public function isAvailableForUser(?User $user): bool
    {
        if (!$user) {
            // Для промо-акций (type = promotion) пользователь не требуется
            return $this->type === 'promotion';
        }

        // Проверяем, привязана ли скидка к пользователю
        $userDiscount = $this->users()->where('user_id', $user->id)->first();
        
        if (!$userDiscount && $this->type !== 'promotion') {
            return false;
        }

        // Проверяем лимит использований для пользователя
        if ($this->usage_per_user && $userDiscount) {
            if ($userDiscount->pivot->used_count >= $this->usage_per_user) {
                return false;
            }
        }

        return true;
    }

    /**
     * Проверить, действительна ли скидка
     */
    public function isValid(?User $user = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Проверяем дату начала (если есть)
        if ($this->start_date && $this->start_date instanceof Carbon && $this->start_date->isFuture()) {
            return false;
        }

        // Проверяем дату окончания (если есть)
        if ($this->end_date && $this->end_date instanceof Carbon && $this->end_date->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($user && !$this->isAvailableForUser($user)) {
            return false;
        }

        return true;
    }
}