<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Promotion;
use Illuminate\Support\Facades\Auth;

class PriceCalculatorService
{
    public function calculateForProduct(Product $product, ?User $user = null)
    {
        $user = $user ?? Auth::user();
        $basePrice = $product->price;
        
        // Акции на товар (из promotions)
        $promotionDiscount = $this->getMaxPromotionDiscount($product);
        $priceWithPromotions = $this->applyDiscount($basePrice, $promotionDiscount);
        
        // Персональная скидка пользователя (из discounts)
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
    
  protected function getMaxPromotionDiscount(Product $product)
    {
        return Promotion::whereHas('products', function($query) use ($product) {
                $query->where('product_id', $product->id);
            })
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
    }
    
   protected function getPersonalDiscount(User $user, Product $product)
    {
        $activeDiscounts = $user->activeDiscounts()->get();
        
        $applicableDiscounts = $activeDiscounts->filter(function ($discount) use ($product) {
            return $discount->appliesToProduct($product);
        });
        
        if ($applicableDiscounts->isEmpty()) {
            return 0;
        }

        // Возвращаем максимальную доступную скидку
        return $applicableDiscounts->max('value') ?? 0;
    }

    /**
     * Получить скидки пользователя для конкретного товара
     */
    public function getProductDiscounts(User $user, Product $product)
    {
        $discounts = $this->getUserDiscounts($user);
        
        return $discounts->filter(function ($discount) use ($product) {
            return $discount->appliesToProduct($product);
        });
    }

    /**
     * Получить все активные скидки пользователя
     */
    public function getUserDiscounts(User $user)
    {
        return $user->activeDiscounts()
            ->with(['products' => function($query) {
                // Загружаем товары только для не-глобальных скидок
                $query->where('is_global', false);
            }])
            ->get();
    }
    
    protected function applyDiscount($price, $discount)
    {
        return $price * (1 - $discount / 100);
    }
}