<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PricingService
{
    private ?Collection $activePromotions = null;

    /** @var array<int, Collection> */
    private array $personalDiscountsByUser = [];

    /** @var array<int, array<int, int>> */
    private array $promotionUsageByUser = [];

    private const PERSONAL_TYPES = [
        Discount::TYPE_PERSONAL,
        Discount::TYPE_FIRST_ORDER,
        Discount::TYPE_LOYALTY,
        Discount::TYPE_REFERRAL,
    ];

    /**
     * Рассчитывает актуальную стоимость корзины. Ничего не записывает в БД
     * и не расходует лимиты скидок.
     */
    public function quoteOrder(
        Order $order,
        User $user,
        float $shippingCost = 0,
        ?array $selection = null
    ): array {
        $order->loadMissing([
            'items.product.sub_subcategories.subcategory.category',
            'gifts.items',
        ]);

        if ($order->items->isEmpty()) {
            throw new DomainException('Корзина пуста');
        }

        $giftMarkupTotal = $this->money($order->gifts->sum(
            fn ($gift) => (float) $gift->markup_unit_amount * (int) $gift->quantity
        ));
        $baseSubtotal = $this->money($order->items->sum(
            fn ($item) => $item->product->baseTotalForQuantity((int) $item->quantity)
        ) + $giftMarkupTotal);

        $promotions = $this->activePromotions();

        $plannedUsage = [];
        $lines = [];

        foreach ($order->items as $item) {
            $product = $item->product;
            $quantity = (int) $item->quantity;
            $product->assertValidSaleQuantity($quantity);
            $unitPrice = $this->money((float) $product->price);
            $priceUnitQuantity = $product->priceUnitQuantity();
            $discountUses = $product->discountUsesForQuantity($quantity);
            $lineBase = $product->baseTotalForQuantity($quantity);

            $promotion = $this->bestProductDiscount(
                $promotions,
                $product,
                $user,
                $lineBase,
                $discountUses,
                $baseSubtotal,
                $plannedUsage
            );

            $promotionAmount = $promotion
                ? $this->discountAmount($promotion, $lineBase, $discountUses)
                : 0.0;

            if ($promotion) {
                $plannedUsage[$promotion->id] = ($plannedUsage[$promotion->id] ?? 0) + $discountUses;
            }

            $afterPromotion = $this->money($lineBase - $promotionAmount);
            $lines[$item->id] = [
                'order_product_id' => $item->id,
                'product_id' => $product->id,
                'order_gift_id' => $item->order_gift_id ? (int) $item->order_gift_id : null,
                'quantity' => $quantity,
                'stock_unit' => $product->stockUnit(),
                'sale_step' => $product->saleStep(),
                'price_unit_quantity' => $priceUnitQuantity,
                'discount_uses' => $discountUses,
                'unit_price' => $unitPrice,
                'base_total' => $lineBase,
                'promotion' => $this->discountData($promotion),
                'promotion_discount_amount' => $promotionAmount,
                'selected_discount' => null,
                'selected_discount_amount' => 0.0,
                'final_unit_price' => $this->effectiveUnitPrice(
                    $afterPromotion,
                    $quantity,
                    $priceUnitQuantity
                ),
                'final_total' => $afterPromotion,
            ];
        }

        $promotionDiscount = $this->money(array_sum(array_column($lines, 'promotion_discount_amount')));
        $afterPromotionSubtotal = $this->money($baseSubtotal - $promotionDiscount);
        $selected = $this->resolveSelection($selection, $user, $afterPromotionSubtotal);

        $personalDiscount = 0.0;
        $cartDiscount = 0.0;
        $shippingDiscount = 0.0;

        if ($selected && in_array($selected->type, self::PERSONAL_TYPES, true)) {
            $eligibleLineIds = collect($lines)
                ->filter(fn (array $line) => $this->appliesToProduct(
                    $selected,
                    $order->items->firstWhere('id', $line['order_product_id'])->product
                ))
                ->keys();

            if ($eligibleLineIds->isEmpty()) {
                throw new DomainException('Выбранная скидка не применяется к товарам в корзине');
            }

            $requiredUses = (int) $eligibleLineIds->sum(
                fn ($lineId) => $lines[$lineId]['discount_uses']
            );
            $this->assertUsageAvailable($selected, $user, $requiredUses, $plannedUsage[$selected->id] ?? 0);

            foreach ($eligibleLineIds as $lineId) {
                $line = $lines[$lineId];
                $amount = $this->discountAmount(
                    $selected,
                    $line['final_total'],
                    $line['discount_uses']
                );
                $line['selected_discount'] = $this->discountData($selected);
                $line['selected_discount_amount'] = $amount;
                $line['final_total'] = $this->money($line['final_total'] - $amount);
                $line['final_unit_price'] = $this->effectiveUnitPrice(
                    $line['final_total'],
                    $line['quantity'],
                    $line['price_unit_quantity']
                );
                $lines[$lineId] = $line;
                $personalDiscount = $this->money($personalDiscount + $amount);
            }

            $plannedUsage[$selected->id] = ($plannedUsage[$selected->id] ?? 0) + $requiredUses;
        } elseif ($selected?->type === Discount::TYPE_CART) {
            $this->assertUsageAvailable($selected, $user, 1, $plannedUsage[$selected->id] ?? 0);
            $cartDiscount = $this->discountAmount($selected, $afterPromotionSubtotal, 1);
            $plannedUsage[$selected->id] = ($plannedUsage[$selected->id] ?? 0) + 1;
        } elseif ($selected?->type === Discount::TYPE_SHIPPING) {
            $this->assertUsageAvailable($selected, $user, 1, $plannedUsage[$selected->id] ?? 0);
            $shippingDiscount = $this->discountAmount($selected, $shippingCost, 1);
            $plannedUsage[$selected->id] = ($plannedUsage[$selected->id] ?? 0) + 1;
        }

        $itemsTotal = $this->money(
            array_sum(array_column($lines, 'final_total')) + $giftMarkupTotal
        );
        $finalTotal = $this->money(
            max(0, $itemsTotal - $cartDiscount) + max(0, $shippingCost - $shippingDiscount)
        );

        $giftQuotes = $order->gifts->map(function ($gift) use ($lines): array {
            $giftLines = collect($lines)->where('order_gift_id', (int) $gift->id);
            $componentsBase = $this->money($giftLines->sum('base_total'));
            $componentsFinal = $this->money($giftLines->sum('final_total'));
            $markup = $this->money((float) $gift->markup_unit_amount * (int) $gift->quantity);

            return [
                'order_gift_id' => (int) $gift->id,
                'quantity' => (int) $gift->quantity,
                'markup_unit_amount' => $this->money((float) $gift->markup_unit_amount),
                'markup_total_amount' => $markup,
                'components_base_total' => $componentsBase,
                'components_discount_amount' => $this->money($componentsBase - $componentsFinal),
                'total_price' => $this->money($componentsFinal + $markup),
            ];
        })->values()->all();

        return [
            'currency' => 'RUB',
            'products_total' => $baseSubtotal,
            'gift_markup_total' => $giftMarkupTotal,
            'promotion_discount' => $promotionDiscount,
            'personal_discount' => $personalDiscount,
            'cart_discount' => $cartDiscount,
            'shipping_cost' => $this->money($shippingCost),
            'shipping_discount' => $shippingDiscount,
            'final_total' => $finalTotal,
            'selected_discount' => $this->discountData($selected),
            'lines' => array_values($lines),
            'gifts' => $giftQuotes,
            'usages' => collect($plannedUsage)
                ->map(fn (int $uses, int $discountId) => [
                    'discount_id' => $discountId,
                    'uses' => $uses,
                ])
                ->values()
                ->all(),
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Записывает снимок расчёта в корзину/заказ. Лимиты расходуются отдельно.
     */
    public function applyQuoteToOrder(Order $order, array $quote): void
    {
        foreach ($quote['lines'] as $line) {
            $promotion = $line['promotion'];
            $selected = $line['selected_discount'];
            $promotionPercent = $promotion && $promotion['value_type'] === Discount::VALUE_PERCENT
                ? $promotion['value']
                : 0;
            $personalPercent = $selected && $selected['value_type'] === Discount::VALUE_PERCENT
                ? $selected['value']
                : 0;

            $order->items()->whereKey($line['order_product_id'])->update([
                'unit_price' => $line['unit_price'],
                'stock_unit' => $line['stock_unit'],
                'sale_step' => $line['sale_step'],
                'price_unit_quantity' => $line['price_unit_quantity'],
                'promotion_discount_id' => $promotion['id'] ?? null,
                'selected_discount_id' => $selected['id'] ?? null,
                'promotion_discount_percent' => $promotionPercent,
                'personal_discount_percent' => $personalPercent,
                'promotion_discount_amount' => $line['promotion_discount_amount'],
                'selected_discount_amount' => $line['selected_discount_amount'],
                'final_unit_price' => $line['final_unit_price'],
                'total_price' => $line['final_total'],
                'pricing_snapshot' => $line,
            ]);
        }

        foreach ($quote['gifts'] ?? [] as $gift) {
            $order->gifts()->whereKey($gift['order_gift_id'])->update([
                'markup_total_amount' => $gift['markup_total_amount'],
                'components_base_total' => $gift['components_base_total'],
                'components_discount_amount' => $gift['components_discount_amount'],
                'total_price' => $gift['total_price'],
            ]);
        }

        $order->update([
            'products_total' => $quote['products_total'],
            'promotion_discount' => $quote['promotion_discount'],
            'personal_discount' => $quote['personal_discount'],
            'cart_discount' => $quote['cart_discount'],
            'shipping_cost' => $quote['shipping_cost'],
            'shipping_discount' => $quote['shipping_discount'],
            'final_total' => $quote['final_total'],
            'discount_id' => $quote['selected_discount']['id'] ?? null,
            'applied_promotion_code' => $quote['selected_discount']['code'] ?? null,
            'pricing_snapshot' => $quote,
        ]);

        $order->load('items.product', 'gifts.items.product');
    }

    /**
     * Вызывается внутри checkout-транзакции после финального расчёта.
     */
    public function consumeUsage(User $user, array $quote): void
    {
        $usages = collect($quote['usages'])->sortBy('discount_id');

        foreach ($usages as $usage) {
            $uses = (int) $usage['uses'];
            $discount = Discount::query()->lockForUpdate()->findOrFail($usage['discount_id']);

            $this->assertDiscountActive($discount);
            $pivot = DB::table('discount_user')
                ->where('discount_id', $discount->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (in_array($discount->type, self::PERSONAL_TYPES, true) && !$pivot) {
                throw new DomainException('Персональная скидка не назначена пользователю');
            }

            $this->assertCounterLimit($discount, (int) $discount->used_count, $uses);
            $userUsed = (int) ($pivot->used_count ?? 0);
            if ($discount->usage_per_user && $userUsed + $uses > $discount->usage_per_user) {
                throw new DomainException("Лимит скидки «{$discount->name}» для пользователя исчерпан");
            }

            $discount->increment('used_count', $uses);
            $newUserUsed = $userUsed + $uses;
            $pivotValues = [
                'used_count' => $newUserUsed,
                'is_used' => $discount->usage_per_user
                    ? $newUserUsed >= $discount->usage_per_user
                    : false,
                'updated_at' => now(),
            ];

            if ($pivot) {
                DB::table('discount_user')
                    ->where('discount_id', $discount->id)
                    ->where('user_id', $user->id)
                    ->update($pivotValues);
            } else {
                DB::table('discount_user')->insert($pivotValues + [
                    'discount_id' => $discount->id,
                    'user_id' => $user->id,
                    'activated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * Возвращает лимиты только для отменённого неоплаченного заказа.
     */
    public function releaseUsage(Order $order): void
    {
        if ($order->status !== Order::STATUS_CANCELLED
            || $order->paid_at
            || $order->discount_usage_released_at) {
            return;
        }

        $snapshot = $order->pricing_snapshot;
        if (!is_array($snapshot) || empty($snapshot['usages'])) {
            return;
        }

        $usages = collect($snapshot['usages'])->sortBy('discount_id');

        foreach ($usages as $usage) {
            $uses = (int) $usage['uses'];
            $discount = Discount::withTrashed()->lockForUpdate()->find($usage['discount_id']);
            if (!$discount) {
                continue;
            }

            $discount->updateQuietly([
                'used_count' => max(0, (int) $discount->used_count - $uses),
            ]);

            $pivot = DB::table('discount_user')
                ->where('discount_id', $discount->id)
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if ($pivot) {
                $newUserUsed = max(0, (int) $pivot->used_count - $uses);
                DB::table('discount_user')
                    ->where('discount_id', $discount->id)
                    ->where('user_id', $order->user_id)
                    ->update([
                        'used_count' => $newUserUsed,
                        'is_used' => $discount->usage_per_user
                            ? $newUserUsed >= $discount->usage_per_user
                            : false,
                        'updated_at' => now(),
                    ]);
            }
        }

        $order->updateQuietly(['discount_usage_released_at' => now()]);
    }

    public function quoteProduct(Product $product, ?User $user = null): array
    {
        $product->loadMissing('sub_subcategories.subcategory.category');
        $promotions = $this->activePromotions();
        $base = $this->money((float) $product->price);
        $promotion = $this->bestProductDiscount(
            $promotions,
            $product,
            $user,
            $base,
            1,
            $base,
            []
        );
        $amount = $promotion ? $this->discountAmount($promotion, $base, 1) : 0.0;
        $final = $this->money($base - $amount);

        return [
            'base_price' => $base,
            'final_price' => $final,
            'promotion_discount_amount' => $amount,
            'promotion_discount' => $this->discountData($promotion),
            'total_discount_percent' => $base > 0
                ? round(($base - $final) / $base * 100, 2)
                : 0.0,
            'has_discount' => $promotion !== null,
        ];
    }

    /**
     * Рассчитывает одну строку с фактическим количеством. Используется Filament,
     * чтобы не дублировать правила развесных товаров и скидок.
     */
    public function quoteProductLine(Product $product, int $quantity, ?User $user = null): array
    {
        $product->loadMissing('sub_subcategories.subcategory.category');
        $product->assertValidSaleQuantity($quantity);

        $baseTotal = $product->baseTotalForQuantity($quantity);
        $discountUses = $product->discountUsesForQuantity($quantity);
        $promotion = $this->bestProductDiscount(
            $this->activePromotions(),
            $product,
            $user,
            $baseTotal,
            $discountUses,
            $baseTotal,
            []
        );
        $promotionAmount = $promotion
            ? $this->discountAmount($promotion, $baseTotal, $discountUses)
            : 0.0;
        $finalTotal = $this->money($baseTotal - $promotionAmount);

        return [
            'product_id' => $product->id,
            'quantity' => $quantity,
            ...$product->measurementSnapshot(),
            'discount_uses' => $discountUses,
            'unit_price' => $this->money((float) $product->price),
            'base_total' => $baseTotal,
            'promotion' => $this->discountData($promotion),
            'promotion_discount_amount' => $promotionAmount,
            'total_discount_percent' => $baseTotal > 0
                ? round($promotionAmount / $baseTotal * 100, 2)
                : 0.0,
            'final_unit_price' => $this->effectiveUnitPrice(
                $finalTotal,
                $quantity,
                $product->priceUnitQuantity()
            ),
            'final_total' => $finalTotal,
        ];
    }

    /**
     * Правила автоматических акций, которые можно включить в подписанный
     * офлайн-снимок продавца. Привязка акции к товару уже проверена сервером.
     */
    public function automaticPromotionRules(Product $product, User $user): array
    {
        $product->loadMissing('sub_subcategories.subcategory.category');
        $userUsage = $this->promotionUsageForUser($user);

        return $this->activePromotions()
            ->filter(fn (Discount $discount) => $this->appliesToProduct($discount, $product))
            ->map(function (Discount $discount) use ($userUsage): array {
                $userUsed = (int) ($userUsage[$discount->id] ?? 0);

                return [
                    'id' => $discount->id,
                    'name' => $discount->name,
                    'value_type' => $discount->value_type,
                    'value' => (float) $discount->value,
                    'min_order_amount' => $discount->min_order_amount !== null
                        ? (float) $discount->min_order_amount
                        : null,
                    'remaining_global_uses' => $discount->usage_limit
                        ? max(0, (int) $discount->usage_limit - (int) $discount->used_count)
                        : null,
                    'remaining_user_uses' => $discount->usage_per_user
                        ? max(0, (int) $discount->usage_per_user - $userUsed)
                        : null,
                    'start_date' => $discount->start_date?->toIso8601String(),
                    'end_date' => $discount->end_date?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    private function promotionUsageForUser(User $user): array
    {
        return $this->promotionUsageByUser[$user->id] ??= DB::table('discount_user')
            ->where('user_id', $user->id)
            ->whereIn('discount_id', $this->activePromotions()->pluck('id'))
            ->pluck('used_count', 'discount_id')
            ->map(fn ($uses): int => (int) $uses)
            ->all();
    }

    /**
     * Физическая офлайн-продажа уже состоялась, поэтому лимит акции не может
     * отклонить её при поздней синхронизации. Счётчики всё равно отражают
     * фактическое использование и могут временно превысить настроенный лимит.
     */
    public function lockSellerOfflineUsage(User $user, array $quote): void
    {
        foreach (collect($quote['usages'] ?? [])->sortBy('discount_id') as $usage) {
            $discount = Discount::withTrashed()
                ->lockForUpdate()
                ->find($usage['discount_id'] ?? 0);

            if (!$discount || $discount->type !== Discount::TYPE_PROMOTION) {
                continue;
            }

            DB::table('discount_user')
                ->where('discount_id', $discount->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
        }
    }

    public function consumeSellerOfflineUsage(User $user, Order $order, array $quote): void
    {
        if ($order->seller_discount_usage_consumed_at !== null) {
            return;
        }

        foreach (collect($quote['usages'] ?? [])->sortBy('discount_id') as $usage) {
            $uses = (int) ($usage['uses'] ?? 0);
            if ($uses <= 0) {
                continue;
            }

            $discount = Discount::withTrashed()
                ->lockForUpdate()
                ->find($usage['discount_id'] ?? 0);

            if (!$discount || $discount->type !== Discount::TYPE_PROMOTION) {
                continue;
            }

            $discount->increment('used_count', $uses);

            $pivot = DB::table('discount_user')
                ->where('discount_id', $discount->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            $newUserUsed = (int) ($pivot->used_count ?? 0) + $uses;
            $pivotValues = [
                'used_count' => $newUserUsed,
                'is_used' => $discount->usage_per_user
                    ? $newUserUsed >= $discount->usage_per_user
                    : false,
                'updated_at' => now(),
            ];

            if ($pivot) {
                DB::table('discount_user')
                    ->where('discount_id', $discount->id)
                    ->where('user_id', $user->id)
                    ->update($pivotValues);
            } else {
                DB::table('discount_user')->insert($pivotValues + [
                    'discount_id' => $discount->id,
                    'user_id' => $user->id,
                    'activated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }

        $order->updateQuietly(['seller_discount_usage_consumed_at' => now()]);
    }

    public function availablePersonalDiscounts(User $user, Product $product): Collection
    {
        $product->loadMissing('sub_subcategories.subcategory.category');

        return $this->personalDiscounts($user)
            ->filter(fn (Discount $discount) => $this->isSelectablePersonal($discount, $user)
                && $this->appliesToProduct($discount, $product))
            ->values();
    }

    private function activePromotions(): Collection
    {
        return $this->activePromotions ??= Discount::query()
            ->active()
            ->ofType(Discount::TYPE_PROMOTION)
            ->with(['products', 'categories', 'subcategories', 'subSubcategories'])
            ->orderBy('id')
            ->get();
    }

    private function personalDiscounts(User $user): Collection
    {
        return $this->personalDiscountsByUser[$user->id] ??= $user->discounts()
            ->whereIn('discounts.type', self::PERSONAL_TYPES)
            ->with(['products', 'categories', 'subcategories', 'subSubcategories'])
            ->get();
    }

    private function resolveSelection(?array $selection, User $user, float $afterPromotionSubtotal): ?Discount
    {
        if (!$selection) {
            return null;
        }

        $type = $selection['type'] ?? null;
        if ($type === 'personal') {
            $discount = Discount::with(['products', 'categories', 'subcategories', 'subSubcategories'])
                ->find($selection['discount_id'] ?? 0);

            if (!$discount || !in_array($discount->type, self::PERSONAL_TYPES, true)) {
                throw new DomainException('Персональная скидка не найдена');
            }
            if (!$this->isSelectablePersonal($discount, $user)) {
                throw new DomainException('Персональная скидка недоступна пользователю');
            }
        } elseif ($type === 'coupon') {
            $code = trim((string) ($selection['code'] ?? ''));
            $discount = Discount::query()
                ->whereIn('type', [Discount::TYPE_CART, Discount::TYPE_SHIPPING])
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
                ->first();

            if (!$discount) {
                throw new DomainException('Промокод не найден');
            }
            $this->assertDiscountActive($discount);
        } else {
            throw new DomainException('Неизвестный способ выбора скидки');
        }

        if ($discount->min_order_amount !== null
            && $afterPromotionSubtotal < (float) $discount->min_order_amount) {
            throw new DomainException(
                "Для скидки «{$discount->name}» нужна сумма после акций от {$discount->min_order_amount} ₽"
            );
        }

        return $discount;
    }

    private function isSelectablePersonal(Discount $discount, User $user): bool
    {
        try {
            $this->assertDiscountActive($discount);
        } catch (DomainException) {
            return false;
        }

        $pivot = $discount->users()->where('users.id', $user->id)->first()?->pivot;
        if (!$pivot) {
            return false;
        }
        if ($discount->usage_per_user && $pivot->used_count >= $discount->usage_per_user) {
            return false;
        }
        if ($discount->type === Discount::TYPE_FIRST_ORDER
            && $user->orders()->realOrders()->exists()) {
            return false;
        }

        return true;
    }

    private function bestProductDiscount(
        Collection $discounts,
        Product $product,
        ?User $user,
        float $amount,
        int $requiredUses,
        float $cartBaseSubtotal,
        array $plannedUsage
    ): ?Discount {
        return $discounts
            ->filter(function (Discount $discount) use (
                $product,
                $user,
                $requiredUses,
                $cartBaseSubtotal,
                $plannedUsage
            ) {
                if (!$this->appliesToProduct($discount, $product)) {
                    return false;
                }
                if ($discount->min_order_amount !== null
                    && $cartBaseSubtotal < (float) $discount->min_order_amount) {
                    return false;
                }

                try {
                    $this->assertUsageAvailable(
                        $discount,
                        $user,
                        $requiredUses,
                        $plannedUsage[$discount->id] ?? 0
                    );
                    return true;
                } catch (DomainException) {
                    return false;
                }
            })
            ->sortByDesc(fn (Discount $discount) => $this->discountAmount($discount, $amount, $requiredUses))
            ->first();
    }

    private function assertUsageAvailable(
        Discount $discount,
        ?User $user,
        int $required,
        int $alreadyPlanned = 0
    ): void {
        $this->assertDiscountActive($discount);
        $this->assertCounterLimit($discount, (int) $discount->used_count + $alreadyPlanned, $required);

        if ($user && $discount->usage_per_user) {
            $userUsed = (int) ($discount->users()
                ->where('users.id', $user->id)
                ->first()?->pivot?->used_count ?? 0);
            if ($userUsed + $alreadyPlanned + $required > $discount->usage_per_user) {
                throw new DomainException("Лимит скидки «{$discount->name}» для пользователя исчерпан");
            }
        }
    }

    private function assertCounterLimit(Discount $discount, int $used, int $required): void
    {
        if ($discount->usage_limit && $used + $required > $discount->usage_limit) {
            throw new DomainException("Лимит скидки «{$discount->name}» исчерпан");
        }
    }

    private function assertDiscountActive(Discount $discount): void
    {
        if (!$discount->is_active
            || ($discount->start_date && $discount->start_date->isFuture())
            || ($discount->end_date && $discount->end_date->isPast())) {
            throw new DomainException("Скидка «{$discount->name}» не действует");
        }
    }

    private function appliesToProduct(Discount $discount, Product $product): bool
    {
        if ($discount->is_global) {
            return true;
        }

        $discount->loadMissing(['products', 'categories', 'subcategories', 'subSubcategories']);
        $product->loadMissing('sub_subcategories.subcategory.category');

        if ($discount->products->contains('id', $product->id)) {
            return true;
        }

        foreach ($product->sub_subcategories as $subSubcategory) {
            if ($discount->subSubcategories->contains('id', $subSubcategory->id)
                || $discount->subcategories->contains('id', $subSubcategory->subcategory_id)
                || $discount->categories->contains('id', $subSubcategory->subcategory?->category_id)) {
                return true;
            }
        }

        return false;
    }

    private function discountAmount(Discount $discount, float $amount, int $applications): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $applications = max(1, $applications);
        $unitAmount = $amount / $applications;
        $unitDiscount = $discount->value_type === Discount::VALUE_FIXED
            ? (float) $discount->value
            : $unitAmount * ((float) $discount->value / 100);
        $raw = $this->money(min($unitAmount, $unitDiscount)) * $applications;

        return $this->money(min($amount, $raw));
    }

    private function discountData(?Discount $discount): ?array
    {
        if (!$discount) {
            return null;
        }

        return [
            'id' => $discount->id,
            'name' => $discount->name,
            'type' => $discount->type,
            'value_type' => $discount->value_type,
            'value' => (float) $discount->value,
            'code' => $discount->code,
        ];
    }

    private function money(float|int $amount): float
    {
        return round((float) $amount, 2);
    }

    private function effectiveUnitPrice(
        float $lineTotal,
        int $quantity,
        int $priceUnitQuantity
    ): float {
        if ($quantity <= 0) {
            return 0.0;
        }

        return $this->money($lineTotal * $priceUnitQuantity / $quantity);
    }
}
