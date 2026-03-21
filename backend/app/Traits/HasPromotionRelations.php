<?php
// app/Traits/HasPromotionRelations.php

namespace App\Traits;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_Subcategory;
use App\Models\Product;

trait HasPromotionRelations
{
    /**
     * Полиморфные связи для акции
     */
    public function categories()
    {
        return $this->morphedByMany(Category::class, 'promotionable');
    }

    public function subcategories()
    {
        return $this->morphedByMany(Subcategory::class, 'promotionable');
    }

    public function subSubcategories()
    {
        return $this->morphedByMany(Sub_Subcategory::class, 'promotionable');
    }

    public function products()
    {
        return $this->morphedByMany(Product::class, 'promotionable');
    }

    /**
     * Проверить, применяется ли акция к конкретному продукту
     */
    public function appliesToProduct(Product $product): bool
    {
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
     * Получить все связанные модели
     */
    public function getRelatedModelsCount(): array
    {
        return [
            'categories' => $this->categories()->count(),
            'subcategories' => $this->subcategories()->count(),
            'sub_subcategories' => $this->subSubcategories()->count(),
            'products' => $this->products()->count(),
        ];
    }
}