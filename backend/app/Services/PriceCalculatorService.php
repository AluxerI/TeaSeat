<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Адаптер для старых ресурсов API и Filament.
 * Новая бизнес-логика расчёта находится только в PricingService.
 */
class PriceCalculatorService
{
    public function __construct(protected PricingService $pricingService)
    {
    }

    public function calculateForProduct(Product $product, ?User $user = null): array
    {
        $quote = $this->pricingService->quoteProduct($product, $user ?? Auth::user());
        $promotion = $quote['promotion_discount'];

        return [
            'base_price' => $quote['base_price'],
            'price_with_promotions' => $quote['final_price'],
            'final_price' => $quote['final_price'],
            // Старые поля оставлены для обратной совместимости фронтенда/Filament.
            'promotion_discount' => $promotion && $promotion['value_type'] === Discount::VALUE_PERCENT
                ? $promotion['value']
                : $quote['total_discount_percent'],
            'personal_discount' => 0.0,
            'total_discount_percent' => $quote['total_discount_percent'],
            'has_discount' => $quote['has_discount'],
            'promotion' => $promotion,
        ];
    }

    public function getMaxPromotionDiscount(Product $product): float
    {
        return (float) $this->calculateForProduct($product)['promotion_discount'];
    }

    public function getPersonalDiscount(User $user, Product $product): float
    {
        $discount = $this->pricingService
            ->availablePersonalDiscounts($user, $product)
            ->sortByDesc(fn (Discount $item) => (float) $item->value)
            ->first();

        return $discount && $discount->value_type === Discount::VALUE_PERCENT
            ? (float) $discount->value
            : 0.0;
    }

    public function getUserDiscounts(User $user): Collection
    {
        return $user->activeDiscounts()
            ->with(['products', 'categories', 'subcategories', 'subSubcategories'])
            ->get()
            ->filter(fn (Discount $discount) => $discount->isValid($user)
                && ($discount->type !== Discount::TYPE_FIRST_ORDER
                    || !$user->orders()->realOrders()->exists()))
            ->values();
    }

    public function getProductDiscounts(User $user, Product $product): Collection
    {
        return $this->pricingService->availablePersonalDiscounts($user, $product);
    }

    public function clearProductCache(Product $product): void
    {
        // PricingService намеренно не кеширует скидки: лимиты должны быть актуальны.
    }

    public function clearUserProductCache(User $user, Product $product): void
    {
        // PricingService намеренно не кеширует скидки: лимиты должны быть актуальны.
    }

    public function getAppliedDiscountObject(Product $product, ?User $user = null): ?Discount
    {
        $discountId = $this->pricingService->quoteProduct($product, $user ?? Auth::user())
            ['promotion_discount']['id'] ?? null;

        return $discountId ? Discount::find($discountId) : null;
    }

    public function getAllApplicableDiscounts(Product $product, ?User $user = null): Collection
    {
        $promotion = $this->getAppliedDiscountObject($product, $user);
        $personal = $user
            ? $this->pricingService->availablePersonalDiscounts($user, $product)
            : collect();

        return collect([$promotion])->filter()->concat($personal)->unique('id')->values();
    }
}
