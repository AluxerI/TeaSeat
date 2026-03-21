<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Promotion;
use App\Models\Discount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PriceCalculatorService
{
    /**
     * Рассчитать цену для товара с учётом всех скидок
     */
    public function calculateForProduct(Product $product, ?User $user = null)
    {
        $user = $user ?? Auth::user();
        $basePrice = $product->price;
        
        // Акции на товар (из promotions) - теперь учитываем категории
        $promotionDiscount = $this->getMaxPromotionDiscount($product);
        $priceWithPromotions = $this->applyDiscount($basePrice, $promotionDiscount);
        
        // Персональная скидка пользователя (из discounts) - теперь учитываем категории
        $personalDiscount = $user ? $this->getPersonalDiscount($user, $product) : 0;
        $finalPrice = $this->applyDiscount($priceWithPromotions, $personalDiscount);
        
        return [
            'base_price' => $basePrice,
            'price_with_promotions' => $priceWithPromotions,
            'final_price' => $finalPrice,
            'promotion_discount' => $promotionDiscount,
            'personal_discount' => $personalDiscount,
            'has_discount' => $promotionDiscount > 0 || $personalDiscount > 0
        ];
    }
    
    /**
     * Получить максимальную скидку по акциям для товара
     */
    protected function getMaxPromotionDiscount(Product $product)
    {
        $cacheKey = "product.{$product->id}.promotion_discount";
        
        return Cache::remember($cacheKey, 300, function () use ($product) {
            // Получаем ID всех категорий товара для быстрого поиска
            $categoryIds = $this->getProductCategoryIds($product);
            
            // Ищем акции, которые применяются к товару (прямо или через категории)
            $promotionIds = collect();
            
            // 1. Акции на конкретные товары
            $directPromotions = Promotion::whereHas('products', function($query) use ($product) {
                $query->where('product_id', $product->id);
            })->pluck('id');
            $promotionIds = $promotionIds->merge($directPromotions);
            
            // 2. Акции на категории товара
            if (!empty($categoryIds['categories'])) {
                foreach ($categoryIds['categories'] as $categoryId) {
                    $categoryPromotions = Promotion::whereHas('categories', function($query) use ($categoryId) {
                        $query->where('categories.id', $categoryId);
                    })->pluck('id');
                    $promotionIds = $promotionIds->merge($categoryPromotions);
                }
            }
            
            // 3. Акции на подкатегории товара
            if (!empty($categoryIds['subcategories'])) {
                foreach ($categoryIds['subcategories'] as $subcategoryId) {
                    $subcategoryPromotions = Promotion::whereHas('subcategories', function($query) use ($subcategoryId) {
                        $query->where('subcategories.id', $subcategoryId);
                    })->pluck('id');
                    $promotionIds = $promotionIds->merge($subcategoryPromotions);
                }
            }
            
            // 4. Акции на под-подкатегории товара
            if (!empty($categoryIds['sub_subcategories'])) {
                foreach ($categoryIds['sub_subcategories'] as $subSubcategoryId) {
                    $subSubcategoryPromotions = Promotion::whereHas('subSubcategories', function($query) use ($subSubcategoryId) {
                        $query->where('sub_subcategories.id', $subSubcategoryId);
                    })->pluck('id');
                    $promotionIds = $promotionIds->merge($subSubcategoryPromotions);
                }
            }
            
            if ($promotionIds->isEmpty()) {
                return 0;
            }
            
            // Получаем максимальный процент скидки среди активных акций
            return Promotion::whereIn('id', $promotionIds->unique())
                ->where('is_active', true)
                ->where('type', 'product')
                ->where(function($query) {
                    $query->whereNull('start_date')
                          ->orWhere('start_date', '<=', now());
                })
                ->where(function($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                })
                ->max('discount_percent') ?? 0;
        });
    }
    
    /**
     * Получить персональную скидку пользователя для товара
     */
    protected function getPersonalDiscount(User $user, Product $product)
    {
        $cacheKey = "user.{$user->id}.product.{$product->id}.discount";
        
        return Cache::remember($cacheKey, 300, function () use ($user, $product) {
            $activeDiscounts = $this->getUserDiscounts($user);
            
            $applicableDiscounts = $activeDiscounts->filter(function ($discount) use ($product) {
                return $this->discountAppliesToProduct($discount, $product);
            });
            
            if ($applicableDiscounts->isEmpty()) {
                return 0;
            }

            // Возвращаем максимальную доступную скидку
            return $applicableDiscounts->max('value') ?? 0;
        });
    }

    /**
     * Проверить, применяется ли скидка к товару (с учётом категорий)
     */
    protected function discountAppliesToProduct($discount, Product $product): bool
    {
        // Глобальные скидки применяются ко всем
        if ($discount->is_global) {
            return true;
        }
        
        // Прямая связь с товаром
        if ($discount->products()->where('product_id', $product->id)->exists()) {
            return true;
        }
        
        // Проверяем связи через категории
        $categoryIds = $this->getProductCategoryIds($product);
        
        // Проверка на категории
        if (!empty($categoryIds['categories'])) {
            foreach ($categoryIds['categories'] as $categoryId) {
                if ($discount->categories()->where('categories.id', $categoryId)->exists()) {
                    return true;
                }
            }
        }
        
        // Проверка на подкатегории
        if (!empty($categoryIds['subcategories'])) {
            foreach ($categoryIds['subcategories'] as $subcategoryId) {
                if ($discount->subcategories()->where('subcategories.id', $subcategoryId)->exists()) {
                    return true;
                }
            }
        }
        
        // Проверка на под-подкатегории
        if (!empty($categoryIds['sub_subcategories'])) {
            foreach ($categoryIds['sub_subcategories'] as $subSubcategoryId) {
                if ($discount->subSubcategories()->where('sub_subcategories.id', $subSubcategoryId)->exists()) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Получить ID всех категорий товара
     */
    protected function getProductCategoryIds(Product $product): array
    {
        $cacheKey = "product.{$product->id}.category_ids";
        
        return Cache::remember($cacheKey, 3600, function () use ($product) {
            $categories = [];
            $subcategories = [];
            $subSubcategories = [];
            
            foreach ($product->sub_subcategories as $subSub) {
                $subSubcategories[] = $subSub->id;
                
                if ($subSub->subcategory) {
                    $subcategories[] = $subSub->subcategory_id;
                    
                    if ($subSub->subcategory->category) {
                        $categories[] = $subSub->subcategory->category_id;
                    }
                }
            }
            
            return [
                'categories' => array_unique($categories),
                'subcategories' => array_unique($subcategories),
                'sub_subcategories' => array_unique($subSubcategories),
            ];
        });
    }

    /**
     * Получить все активные скидки пользователя
     */
    public function getUserDiscounts(User $user)
    {
        return $user->activeDiscounts()
            ->with(['products', 'categories', 'subcategories', 'subSubcategories'])
            ->get();
    }

    /**
     * Получить скидки пользователя для конкретного товара
     */
    public function getProductDiscounts(User $user, Product $product)
    {
        $discounts = $this->getUserDiscounts($user);
        
        return $discounts->filter(function ($discount) use ($product) {
            return $this->discountAppliesToProduct($discount, $product);
        });
    }

    /**
     * Применить процентную скидку к цене
     */
    protected function applyDiscount($price, $discount)
    {
        return $price * (1 - $discount / 100);
    }

    /**
     * Очистить кеш цен для товара
     */
    public function clearProductCache(Product $product): void
    {
        Cache::forget("product.{$product->id}.promotion_discount");
        Cache::forget("product.{$product->id}.category_ids");
    }

    /**
     * Очистить кеш цен для пользователя и товара
     */
    public function clearUserProductCache(User $user, Product $product): void
    {
        Cache::forget("user.{$user->id}.product.{$product->id}.discount");
    }
}