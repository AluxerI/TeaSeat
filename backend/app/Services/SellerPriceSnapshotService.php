<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;

class SellerPriceSnapshotService
{
    private const VERSION = 1;

    public function __construct(protected PricingService $pricingService)
    {
    }

    public function snapshot(Product $product, User $user): array
    {
        $issuedAt = CarbonImmutable::now();
        $expiresAt = $issuedAt->addHours(
            max(1, (int) config('seller.price_snapshot_ttl_hours', 48))
        );

        $payload = [
            'version' => self::VERSION,
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'unit_price' => round((float) $product->price, 2),
            'stock_unit' => $product->stockUnit(),
            'sale_step' => $product->saleStep(),
            'price_unit_quantity' => $product->priceUnitQuantity(),
            'automatic_promotions' => $this->pricingService
                ->automaticPromotionRules($product, $user),
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        return [
            'pricing' => $payload,
            'pricing_token' => $this->encode($payload),
        ];
    }

    /**
     * @param array<int, array{product_id:int, quantity:int, pricing_token:string}> $items
     */
    public function quote(array $items, User $user, CarbonImmutable $occurredAt): array
    {
        $verified = [];
        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);
            if (!$product) {
                throw new DomainException('Один из товаров больше не существует');
            }

            $quantity = (int) $item['quantity'];
            $payload = $this->decodeAndVerify(
                (string) $item['pricing_token'],
                $user,
                $product,
                $occurredAt
            );
            $product->assertValidSaleQuantity($quantity);

            if ($payload['stock_unit'] !== $product->stockUnit()
                || (int) $payload['sale_step'] !== $product->saleStep()
                || (int) $payload['price_unit_quantity'] !== $product->priceUnitQuantity()) {
                throw new DomainException(
                    "Единица продажи товара «{$product->name}» изменилась. Обновите каталог PWA"
                );
            }

            $verified[] = [
                'product' => $product,
                'quantity' => $quantity,
                'snapshot' => $payload,
            ];
        }

        $baseSubtotal = $this->money(collect($verified)->sum(function (array $line): float {
            return (float) $line['snapshot']['unit_price']
                * $line['quantity']
                / max(1, (int) $line['snapshot']['price_unit_quantity']);
        }));
        $plannedUsage = [];
        $lines = [];

        foreach ($verified as $verifiedLine) {
            /** @var Product $product */
            $product = $verifiedLine['product'];
            $quantity = $verifiedLine['quantity'];
            $snapshot = $verifiedLine['snapshot'];
            $discountUses = $snapshot['stock_unit'] === Product::STOCK_UNIT_GRAM
                ? 1
                : $quantity;
            $baseTotal = $this->money(
                (float) $snapshot['unit_price']
                * $quantity
                / max(1, (int) $snapshot['price_unit_quantity'])
            );

            $promotion = collect($snapshot['automatic_promotions'] ?? [])
                ->filter(fn (array $rule): bool => $this->ruleIsAvailable(
                    $rule,
                    $occurredAt,
                    $baseSubtotal,
                    $discountUses,
                    (int) ($plannedUsage[$rule['id']] ?? 0)
                ))
                ->sortByDesc(fn (array $rule): float => $this->discountAmount(
                    $rule,
                    $baseTotal,
                    $discountUses
                ))
                ->first();
            $discountAmount = $promotion
                ? $this->discountAmount($promotion, $baseTotal, $discountUses)
                : 0.0;

            if ($promotion) {
                $plannedUsage[$promotion['id']] =
                    (int) ($plannedUsage[$promotion['id']] ?? 0) + $discountUses;
            }

            $finalTotal = $this->money($baseTotal - $discountAmount);
            $lines[] = [
                'product_id' => (int) $product->id,
                'quantity' => $quantity,
                'stock_unit' => $snapshot['stock_unit'],
                'sale_step' => (int) $snapshot['sale_step'],
                'price_unit_quantity' => (int) $snapshot['price_unit_quantity'],
                'discount_uses' => $discountUses,
                'unit_price' => $this->money((float) $snapshot['unit_price']),
                'base_total' => $baseTotal,
                'promotion' => $promotion ? [
                    'id' => (int) $promotion['id'],
                    'name' => $promotion['name'],
                    'type' => Discount::TYPE_PROMOTION,
                    'value_type' => $promotion['value_type'],
                    'value' => (float) $promotion['value'],
                    'code' => null,
                ] : null,
                'promotion_discount_amount' => $discountAmount,
                'selected_discount' => null,
                'selected_discount_amount' => 0.0,
                'final_unit_price' => $quantity > 0
                    ? $this->money($finalTotal * (int) $snapshot['price_unit_quantity'] / $quantity)
                    : 0.0,
                'final_total' => $finalTotal,
                'signed_price_snapshot' => $snapshot,
            ];
        }

        $promotionDiscount = $this->money(array_sum(array_column(
            $lines,
            'promotion_discount_amount'
        )));
        $finalTotal = $this->money(array_sum(array_column($lines, 'final_total')));

        return [
            'currency' => 'RUB',
            'source' => 'seller_signed_snapshot',
            'products_total' => $baseSubtotal,
            'promotion_discount' => $promotionDiscount,
            'personal_discount' => 0.0,
            'cart_discount' => 0.0,
            'shipping_cost' => 0.0,
            'shipping_discount' => 0.0,
            'final_total' => $finalTotal,
            'selected_discount' => null,
            'lines' => $lines,
            'usages' => collect($plannedUsage)
                ->map(fn (int $uses, int $discountId): array => [
                    'discount_id' => $discountId,
                    'uses' => $uses,
                ])
                ->values()
                ->all(),
            'calculated_at' => now()->toIso8601String(),
            'sale_occurred_at' => $occurredAt->toIso8601String(),
        ];
    }

    private function decodeAndVerify(
        string $token,
        User $user,
        Product $product,
        CarbonImmutable $occurredAt
    ): array {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new DomainException('Некорректный подписанный снимок цены');
        }

        [$encodedPayload, $providedSignature] = $parts;
        $expectedSignature = $this->signature($encodedPayload);
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new DomainException('Подпись снимка цены недействительна');
        }

        $json = $this->base64UrlDecode($encodedPayload);
        $payload = json_decode($json, true);
        if (!is_array($payload)
            || (int) ($payload['version'] ?? 0) !== self::VERSION
            || (int) ($payload['user_id'] ?? 0) !== (int) $user->id
            || (int) ($payload['product_id'] ?? 0) !== (int) $product->id) {
            throw new DomainException('Снимок цены не относится к продавцу или товару');
        }

        try {
            $issuedAt = CarbonImmutable::parse($payload['issued_at']);
            $expiresAt = CarbonImmutable::parse($payload['expires_at']);
        } catch (\Throwable) {
            throw new DomainException('В снимке цены указано некорректное время');
        }

        $skewMinutes = max(0, (int) config('seller.clock_skew_minutes', 5));
        if ($occurredAt->gt(CarbonImmutable::now()->addMinutes($skewMinutes))) {
            throw new DomainException(
                'Время физической продажи не может быть позже серверного времени'
            );
        }
        if ($occurredAt->lt($issuedAt->subMinutes($skewMinutes))
            || $occurredAt->gt($expiresAt->addMinutes($skewMinutes))) {
            throw new DomainException('На момент продажи снимок цены ещё не действовал или уже истёк');
        }

        return $payload;
    }

    private function ruleIsAvailable(
        array $rule,
        CarbonImmutable $occurredAt,
        float $baseSubtotal,
        int $requiredUses,
        int $plannedUses
    ): bool {
        if (($rule['start_date'] ?? null)
            && $occurredAt->lt(CarbonImmutable::parse($rule['start_date']))) {
            return false;
        }
        if (($rule['end_date'] ?? null)
            && $occurredAt->gt(CarbonImmutable::parse($rule['end_date']))) {
            return false;
        }
        if (($rule['min_order_amount'] ?? null) !== null
            && $baseSubtotal < (float) $rule['min_order_amount']) {
            return false;
        }
        if (($rule['remaining_global_uses'] ?? null) !== null
            && $plannedUses + $requiredUses > (int) $rule['remaining_global_uses']) {
            return false;
        }
        if (($rule['remaining_user_uses'] ?? null) !== null
            && $plannedUses + $requiredUses > (int) $rule['remaining_user_uses']) {
            return false;
        }

        return true;
    }

    private function discountAmount(array $rule, float $amount, int $applications): float
    {
        $applications = max(1, $applications);
        $unitAmount = $amount / $applications;
        $unitDiscount = $rule['value_type'] === Discount::VALUE_FIXED
            ? (float) $rule['value']
            : $unitAmount * ((float) $rule['value'] / 100);

        return $this->money(min($amount, min($unitAmount, $unitDiscount) * $applications));
    }

    private function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $encodedPayload = $this->base64UrlEncode($json);

        return $encodedPayload . '.' . $this->signature($encodedPayload);
    }

    private function signature(string $encodedPayload): string
    {
        return $this->base64UrlEncode(hash_hmac(
            'sha256',
            $encodedPayload,
            (string) config('app.key'),
            true
        ));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new DomainException('Некорректный снимок цены');
        }

        return $decoded;
    }

    private function money(float|int $amount): float
    {
        return round((float) $amount, 2);
    }
}
