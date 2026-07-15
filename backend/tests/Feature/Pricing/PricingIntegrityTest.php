<?php

namespace Tests\Feature\Pricing;

use App\Models\Brand;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PricingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_best_automatic_promotion_is_selected_by_saved_amount(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Чай', 100);
        $cart = $this->createCart($user, $product, 2);

        $percent = $this->createDiscount([
            'name' => '10 процентов',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_PERCENT,
            'value' => 10,
        ]);
        $fixed = $this->createDiscount([
            'name' => '15 рублей с единицы',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_FIXED,
            'value' => 15,
        ]);
        $percent->products()->attach($product);
        $fixed->products()->attach($product);

        $quote = app(PricingService::class)->quoteOrder($cart, $user);

        $this->assertSame($fixed->id, $quote['lines'][0]['promotion']['id']);
        $this->assertSame(30.0, $quote['promotion_discount']);
        $this->assertSame(170.0, $quote['final_total']);
        $this->assertSame([
            ['discount_id' => $fixed->id, 'uses' => 2],
        ], $quote['usages']);
    }

    public function test_personal_discount_is_applied_after_automatic_promotion(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Ассам', 1000);
        $cart = $this->createCart($user, $product, 1);

        $promotion = $this->createDiscount([
            'name' => 'Акция 10%',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_PERCENT,
            'value' => 10,
            'is_global' => true,
        ]);
        $personal = $this->createDiscount([
            'name' => 'Персональная 20%',
            'type' => Discount::TYPE_PERSONAL,
            'value_type' => Discount::VALUE_PERCENT,
            'value' => 20,
            'is_global' => true,
            'usage_per_user' => 3,
        ]);
        $personal->users()->attach($user);

        $quote = app(PricingService::class)->quoteOrder($cart, $user, 0, [
            'type' => 'personal',
            'discount_id' => $personal->id,
        ]);

        $this->assertSame($promotion->id, $quote['lines'][0]['promotion']['id']);
        $this->assertSame(100.0, $quote['promotion_discount']);
        $this->assertSame(180.0, $quote['personal_discount']);
        $this->assertSame(720.0, $quote['final_total']);
    }

    public function test_fixed_cart_coupon_is_applied_once_after_promotions(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Набор конфет', 1000);
        $cart = $this->createCart($user, $product, 1);

        $this->createDiscount([
            'name' => 'Акция 10%',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_PERCENT,
            'value' => 10,
            'is_global' => true,
        ]);
        $coupon = $this->createDiscount([
            'name' => 'Минус 100 рублей',
            'type' => Discount::TYPE_CART,
            'value_type' => Discount::VALUE_FIXED,
            'value' => 100,
            'code' => 'CART100',
            'min_order_amount' => 850,
        ]);

        $quote = app(PricingService::class)->quoteOrder($cart, $user, 0, [
            'type' => 'coupon',
            'code' => 'cart100',
        ]);

        $this->assertSame($coupon->id, $quote['selected_discount']['id']);
        $this->assertSame(100.0, $quote['cart_discount']);
        $this->assertSame(800.0, $quote['final_total']);
        $this->assertContains(
            ['discount_id' => $coupon->id, 'uses' => 1],
            $quote['usages']
        );
    }

    public function test_product_discount_is_not_partially_applied_when_units_exceed_remaining_limit(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Мармелад', 100);
        $cart = $this->createCart($user, $product, 10);
        $promotion = $this->createDiscount([
            'name' => 'Ограниченная акция',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_FIXED,
            'value' => 20,
            'usage_limit' => 10,
            'used_count' => 1,
        ]);
        $promotion->products()->attach($product);

        $quote = app(PricingService::class)->quoteOrder($cart, $user);

        $this->assertNull($quote['lines'][0]['promotion']);
        $this->assertSame(1000.0, $quote['final_total']);
        $this->assertSame([], $quote['usages']);
    }

    public function test_minimum_amount_is_checked_after_automatic_promotions(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Шоколад', 1000);
        $cart = $this->createCart($user, $product, 1);

        $this->createDiscount([
            'name' => 'Акция 10%',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_PERCENT,
            'value' => 10,
            'is_global' => true,
        ]);
        $this->createDiscount([
            'name' => 'Порог 950 рублей',
            'type' => Discount::TYPE_CART,
            'value_type' => Discount::VALUE_FIXED,
            'value' => 100,
            'code' => 'MIN950',
            'min_order_amount' => 950,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('нужна сумма после акций');

        app(PricingService::class)->quoteOrder($cart, $user, 0, [
            'type' => 'coupon',
            'code' => 'MIN950',
        ]);
    }

    public function test_shipping_coupon_reduces_only_shipping_cost(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Подарочная банка', 500);
        $cart = $this->createCart($user, $product, 1);
        $coupon = $this->createDiscount([
            'name' => 'Доставка дешевле',
            'type' => Discount::TYPE_SHIPPING,
            'value_type' => Discount::VALUE_FIXED,
            'value' => 150,
            'code' => 'SHIP150',
        ]);

        $quote = app(PricingService::class)->quoteOrder($cart, $user, 200, [
            'type' => 'coupon',
            'code' => 'SHIP150',
        ]);

        $this->assertSame($coupon->id, $quote['selected_discount']['id']);
        $this->assertSame(0.0, $quote['cart_discount']);
        $this->assertSame(150.0, $quote['shipping_discount']);
        $this->assertSame(550.0, $quote['final_total']);
    }

    public function test_checkout_usage_counts_units_and_unpaid_cancellation_releases_it_once(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Печенье', 100);
        $cart = $this->createCart($user, $product, 10);
        $promotion = $this->createDiscount([
            'name' => 'Последние 10 единиц',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_FIXED,
            'value' => 10,
            'usage_limit' => 10,
        ]);
        $promotion->products()->attach($product);

        $service = app(PricingService::class);
        $quote = $service->quoteOrder($cart, $user);
        $service->applyQuoteToOrder($cart, $quote);
        $service->consumeUsage($user, $quote);

        $this->assertSame(10, (int) $promotion->fresh()->used_count);
        $this->assertSame(
            10,
            (int) $promotion->users()->where('users.id', $user->id)->first()->pivot->used_count
        );

        $cart->update(['status' => Order::STATUS_CANCELLED]);
        $service->releaseUsage($cart->fresh());
        $service->releaseUsage($cart->fresh());

        $this->assertSame(0, (int) $promotion->fresh()->used_count);
        $this->assertSame(
            0,
            (int) $promotion->users()->where('users.id', $user->id)->first()->pivot->used_count
        );
        $this->assertNotNull($cart->fresh()->discount_usage_released_at);
    }

    public function test_user_cannot_select_another_users_personal_discount(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = $this->createProduct('Варенье', 500);
        $cart = $this->createCart($user, $product, 1);
        $personal = $this->createDiscount([
            'name' => 'Чужая скидка',
            'type' => Discount::TYPE_PERSONAL,
            'value_type' => Discount::VALUE_PERCENT,
            'value' => 25,
            'is_global' => true,
        ]);
        $personal->users()->attach($otherUser);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Персональная скидка недоступна пользователю');

        app(PricingService::class)->quoteOrder($cart, $user, 0, [
            'type' => 'personal',
            'discount_id' => $personal->id,
        ]);
    }

    public function test_null_and_blank_discount_codes_are_stored_as_null(): void
    {
        $withoutCode = $this->createDiscount([
            'name' => 'Акция без кода',
            'code' => null,
        ]);
        $withBlankCode = $this->createDiscount([
            'name' => 'Ещё одна акция без кода',
            'code' => '   ',
        ]);

        $this->assertNull($withoutCode->fresh()->code);
        $this->assertNull($withBlankCode->fresh()->code);
    }

    public function test_weighted_product_price_uses_requested_grams_and_keeps_measurement_snapshot(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Развесной чай', 250, [
            'stock_unit' => Product::STOCK_UNIT_GRAM,
            'sale_step' => 10,
            'price_unit_quantity' => 100,
            'weight_grams' => null,
        ]);
        $cart = $this->createCart($user, $product, 110);

        $service = app(PricingService::class);
        $quote = $service->quoteOrder($cart, $user);

        $this->assertSame(275.0, $quote['products_total']);
        $this->assertSame(275.0, $quote['final_total']);
        $this->assertSame(Product::STOCK_UNIT_GRAM, $quote['lines'][0]['stock_unit']);
        $this->assertSame(10, $quote['lines'][0]['sale_step']);
        $this->assertSame(100, $quote['lines'][0]['price_unit_quantity']);
        $this->assertSame(250.0, $quote['lines'][0]['final_unit_price']);

        $service->applyQuoteToOrder($cart, $quote);
        $item = $cart->items()->firstOrFail();

        $this->assertSame(Product::STOCK_UNIT_GRAM, $item->stock_unit);
        $this->assertSame(10, $item->sale_step);
        $this->assertSame(100, $item->price_unit_quantity);
        $this->assertSame('275.00', $item->total_price);
    }

    public function test_fixed_product_discount_is_applied_once_to_weighted_order_line(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Чай 1 кг', 250, [
            'stock_unit' => Product::STOCK_UNIT_GRAM,
            'sale_step' => 10,
            'price_unit_quantity' => 100,
            'weight_grams' => null,
        ]);
        $cart = $this->createCart($user, $product, 1000);
        $promotion = $this->createDiscount([
            'name' => 'Минус 100 рублей с развесного товара',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_FIXED,
            'value' => 100,
            'usage_limit' => 1,
        ]);
        $promotion->products()->attach($product);

        $quote = app(PricingService::class)->quoteOrder($cart, $user);

        $this->assertSame(2500.0, $quote['products_total']);
        $this->assertSame(100.0, $quote['promotion_discount']);
        $this->assertSame(2400.0, $quote['final_total']);
        $this->assertSame(240.0, $quote['lines'][0]['final_unit_price']);
        $this->assertSame([
            ['discount_id' => $promotion->id, 'uses' => 1],
        ], $quote['usages']);
    }

    public function test_selected_personal_discount_also_uses_weighted_line_once(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Весовой чай с личной скидкой', 250, [
            'stock_unit' => Product::STOCK_UNIT_GRAM,
            'sale_step' => 10,
            'price_unit_quantity' => 100,
            'weight_grams' => null,
        ]);
        $cart = $this->createCart($user, $product, 1000);
        $personal = $this->createDiscount([
            'name' => 'Личная скидка 100 рублей',
            'type' => Discount::TYPE_PERSONAL,
            'value_type' => Discount::VALUE_FIXED,
            'value' => 100,
            'usage_per_user' => 1,
            'is_global' => true,
        ]);
        $personal->users()->attach($user);

        $quote = app(PricingService::class)->quoteOrder($cart, $user, 0, [
            'type' => 'personal',
            'discount_id' => $personal->id,
        ]);

        $this->assertSame(100.0, $quote['personal_discount']);
        $this->assertSame(2400.0, $quote['final_total']);
        $this->assertSame([
            ['discount_id' => $personal->id, 'uses' => 1],
        ], $quote['usages']);
    }

    public function test_weighted_quantity_must_be_an_integer_multiple_of_sale_step(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('Чай с шагом 10 г', 250, [
            'stock_unit' => Product::STOCK_UNIT_GRAM,
            'sale_step' => 10,
            'price_unit_quantity' => 100,
            'weight_grams' => null,
        ]);
        $cart = $this->createCart($user, $product, 100);
        $cart->items()->firstOrFail()->update(['quantity' => 105]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('должно быть кратно 10 г');

        app(PricingService::class)->quoteOrder($cart->fresh('items.product'), $user);
    }

    private function createProduct(string $name, float $price, array $attributes = []): Product
    {
        $brand = Brand::create(['name' => "Бренд {$name}"]);

        $product = new Product();
        $product->name = $name;
        $product->brand_id = $brand->id;
        $product->price = $price;
        $product->stock_unit = $attributes['stock_unit'] ?? Product::STOCK_UNIT_PIECE;
        $product->sale_step = $attributes['sale_step'] ?? 1;
        $product->price_unit_quantity = $attributes['price_unit_quantity'] ?? 1;
        $product->weight_grams = array_key_exists('weight_grams', $attributes)
            ? $attributes['weight_grams']
            : 100;
        $product->is_available = true;
        $product->total_quantity = 100;
        $product->save();

        return $product;
    }

    private function createCart(User $user, Product $product, int $quantity): Order
    {
        $baseTotal = $product->baseTotalForQuantity($quantity);
        $cart = Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_CART,
            'products_total' => $baseTotal,
            'final_total' => $baseTotal,
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            ...$product->measurementSnapshot(),
            'unit_price' => $product->price,
            'promotion_discount_percent' => 0,
            'personal_discount_percent' => 0,
            'final_unit_price' => $product->price,
            'total_price' => $baseTotal,
        ]);

        return $cart;
    }

    private function createDiscount(array $attributes): Discount
    {
        return Discount::create($attributes + [
            'description' => null,
            'value' => 10,
            'value_type' => Discount::VALUE_PERCENT,
            'type' => Discount::TYPE_PROMOTION,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => true,
            'is_global' => false,
            'used_count' => 0,
        ]);
    }
}
