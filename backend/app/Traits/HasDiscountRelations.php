<?php
// app/Traits/HasDiscountRelations.php

namespace App\Traits;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use App\Models\Product;
use App\Models\User;

trait HasDiscountRelations
{
    /**
     * Полиморфные связи для скидки
     */
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
        return $this->morphedByMany(User::class, 'discountable');
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

        // Проверяем прямую связь с продуктом
        if ($this->products()->where('product_id', $product->id)->exists()) {
            return true;
        }

        // Проверяем связи через категории
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
     * Получить все связанные модели для отладки или отображения
     */
    public function getRelatedModelsCount(): array
    {
        return [
            'categories' => $this->categories()->count(),
            'subcategories' => $this->subcategories()->count(),
            'sub_subcategories' => $this->subSubcategories()->count(),
            'products' => $this->products()->count(),
            'users' => $this->users()->count(),
        ];
    }
}