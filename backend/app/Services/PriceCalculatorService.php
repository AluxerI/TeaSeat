<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Discount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PriceCalculatorService
{
    /**
     * Рассчитать цену для товара с учётом всех скидок
     */
    public function calculateForProduct(Product $product, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        $basePrice = $product->price;
        
        $promotionDiscount = $this->getMaxPromotionDiscount($product);
        $priceWithPromotions = $this->applyDiscount($basePrice, $promotionDiscount);
        
        $personalDiscount = $user ? $this->getPersonalDiscount($user, $product) : 0;
        $finalPrice = $this->applyDiscount($priceWithPromotions, $personalDiscount);
        
        return [
            'base_price' => $basePrice,
            'price_with_promotions' => $priceWithPromotions,
            'final_price' => $finalPrice,
            'promotion_discount' => $promotionDiscount,
            'personal_discount' => $personalDiscount,
            'total_discount_percent' => $basePrice > 0 ? round(($basePrice - $finalPrice) / $basePrice * 100, 1) : 0,
            'has_discount' => $promotionDiscount > 0 || $personalDiscount > 0
        ];
    }
    
    /**
     * Получить максимальную скидку по акциям для товара (type = 'promotion')
     * 👇 ИЗМЕНЕНО: protected → public
     */
    public function getMaxPromotionDiscount(Product $product): float
    {
        $cacheKey = "product.{$product->id}.promotion_discount";
        
        return Cache::remember($cacheKey, 300, function () use ($product) {
            $categoryIds = $this->getProductCategoryIds($product);
            
            $query = Discount::where('type', Discount::TYPE_PROMOTION)
                ->where('is_active', true)
                ->where(function($q) {
                    $q->whereNull('start_date')->orWhere('start_date', '<=', now());
                })
                ->where(function($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                });
            
            $globalMax = (clone $query)->where('is_global', true)->max('value') ?? 0;
            
            $directMax = (clone $query)
                ->where('is_global', false)
                ->whereHas('products', function($q) use ($product) {
                    $q->where('discountables.discountable_id', $product->id);
                })
                ->max('value') ?? 0;
            
            $categoryMax = 0;
            if (!empty($categoryIds['categories'])) {
                $categoryMax = (clone $query)
                    ->where('is_global', false)
                    ->whereHas('categories', function($q) use ($categoryIds) {
                        $q->whereIn('categories.id', $categoryIds['categories']);
                    })
                    ->max('value') ?? 0;
            }
            
            $subcategoryMax = 0;
            if (!empty($categoryIds['subcategories'])) {
                $subcategoryMax = (clone $query)
                    ->where('is_global', false)
                    ->whereHas('subcategories', function($q) use ($categoryIds) {
                        $q->whereIn('subcategories.id', $categoryIds['subcategories']);
                    })
                    ->max('value') ?? 0;
            }
            
            $subSubcategoryMax = 0;
            if (!empty($categoryIds['sub_subcategories'])) {
                $subSubcategoryMax = (clone $query)
                    ->where('is_global', false)
                    ->whereHas('subSubcategories', function($q) use ($categoryIds) {
                        $q->whereIn('sub_subcategories.id', $categoryIds['sub_subcategories']);
                    })
                    ->max('value') ?? 0;
            }
            
            return max($globalMax, $directMax, $categoryMax, $subcategoryMax, $subSubcategoryMax);
        });
    }
    
    /**
     * Получить персональную скидку пользователя для товара
     * 👇 ИЗМЕНЕНО: protected → public
     */
    public function getPersonalDiscount(User $user, Product $product): float
    {
        $cacheKey = "user.{$user->id}.product.{$product->id}.discount";
        
        return Cache::remember($cacheKey, 300, function () use ($user, $product) {
            $activeDiscounts = $this->getUserDiscounts($user);
            
            $applicableDiscounts = $activeDiscounts->filter(function ($discount) use ($product) {
                return $discount->appliesToProduct($product);
            });
            
            if ($applicableDiscounts->isEmpty()) {
                return 0;
            }

            return (float) ($applicableDiscounts->max('value') ?? 0);
        });
    }

    /**
     * Получить ID всех категорий товара
     * 👇 ИЗМЕНЕНО: protected → public (или оставить protected, не используется извне)
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
     * 👇 УЖЕ public
     */
    public function getUserDiscounts(User $user): \Illuminate\Support\Collection
    {
        return $user->activeDiscounts()
            ->with(['products', 'categories', 'subcategories', 'subSubcategories'])
            ->get();
    }

    /**
     * Получить скидки пользователя для конкретного товара
     */
    public function getProductDiscounts(User $user, Product $product): \Illuminate\Support\Collection
    {
        $discounts = $this->getUserDiscounts($user);
        
        return $discounts->filter(function ($discount) use ($product) {
            return $discount->appliesToProduct($product);
        });
    }

    /**
     * Применить процентную скидку к цене
     */
    protected function applyDiscount(float $price, float $discount): float
    {
        return round($price * (1 - $discount / 100), 2);
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
    
    /**
     * Получить объект применённой скидки для товара
     * 👇 НОВЫЙ МЕТОД (public)
     */
    public function getAppliedDiscountObject(Product $product, ?User $user = null): ?Discount
    {
        $priceData = $this->calculateForProduct($product, $user);
        
        if ($priceData['promotion_discount'] > 0) {
            return Discount::where('type', Discount::TYPE_PROMOTION)
                ->where('value', $priceData['promotion_discount'])
                ->where('is_active', true)
                ->first();
        }
        
        if ($priceData['personal_discount'] > 0 && $user) {
            return Discount::whereIn('type', ['personal', 'first_order', 'loyalty', 'referral'])
                ->where('value', $priceData['personal_discount'])
                ->where('is_active', true)
                ->first();
        }
        
        return null;
    }
    
    /**
     * Получить все скидки, применяющиеся к товару
     * 👇 НОВЫЙ МЕТОД (public)
     */
    public function getAllApplicableDiscounts(Product $product, ?User $user = null): \Illuminate\Support\Collection
    {
        $discounts = $this->getUserDiscounts($user ?? Auth::user());
        
        return $discounts->filter(function ($discount) use ($product) {
            return $discount->appliesToProduct($product);
        });
    }
}