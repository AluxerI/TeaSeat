<?php

namespace App\Services; 

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PriceCalculatorService
{
    public function calculateForProduct(Product $product, ?User $user = null)
    {
        $user = $user ?? Auth::user();
        $basePrice = $product->price;
        
        // Максимальная акция на товар
        $promotionDiscount = $this->getMaxPromotionDiscount($product);
        $priceWithPromotions = $this->applyDiscount($basePrice, $promotionDiscount);
        
        // Персональная скидка пользователя (если есть)
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
        return $product->promotions()
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->max('discount_percent') ?? 0;
    }
    
    protected function getPersonalDiscount(User $user, Product $product)
    {
        return $user->discounts()
            ->where('is_active', true)
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->whereHas('products', function($query) use ($product) {
                $query->where('product_id', $product->id);
            })
            ->value('value') ?? 0;
    }
    
    protected function applyDiscount($price, $discount)
    {
        return $price * (1 - $discount / 100);
    }
}