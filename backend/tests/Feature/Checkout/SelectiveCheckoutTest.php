<?php

namespace Tests\Feature\Checkout;

use App\Models\AddressClient;
use App\Models\Brand;
use App\Models\DeliveryMethod;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelectiveCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_selected_products_and_whole_gift_are_checked_out_while_other_items_remain(): void
    {
        $context = $this->context();
        Sanctum::actingAs($context['user']);

        $cart = Order::factory()->cart()->create(['user_id' => $context['user']->id]);
        $selectedItem = $this->line($cart, $context['products'][0]);
        $remainingItem = $this->line($cart, $context['products'][1]);
        $gift = $cart->gifts()->create([
            'gift_id' => null,
            'client_instance_id' => (string) Str::uuid(),
            'gift_version' => 1,
            'name' => 'Выбранный подарок',
            'quantity' => 1,
            'markup_unit_amount' => 50,
            'markup_total_amount' => 50,
            'layout_snapshot' => [],
        ]);
        $giftComponent = $cart->items()->create([
            'product_id' => $context['products'][2]->id,
            'order_gift_id' => $gift->id,
            'gift_item_client_id' => (string) Str::uuid(),
            'gift_item_quantity' => 1,
            'gift_item_sort_order' => 0,
            'quantity' => 1,
            ...$context['products'][2]->measurementSnapshot(),
            'unit_price' => 300,
            'final_unit_price' => 300,
            'total_price' => 300,
        ]);

        $selection = [
            'cart_item_ids' => [$selectedItem->id],
            'cart_gift_ids' => [$gift->id],
        ];
        $this->postJson('/api/cart/quote', $selection)
            ->assertOk()
            ->assertJsonPath('data.final_total', 450)
            ->assertJsonPath('data.cart_selection.explicit', true)
            ->assertJsonPath('data.cart_selection.cart_item_ids.0', $selectedItem->id)
            ->assertJsonPath('data.cart_selection.cart_gift_ids.0', $gift->id);

        $key = 'selective-checkout-0001';
        $payload = $selection + [
            'shipping_address_id' => $context['address']->id,
            'delivery_method_id' => $context['delivery']->id,
            'payment_method' => Order::PAYMENT_CARD,
        ];
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/checkout', $payload)
            ->assertOk();

        $order = Order::query()
            ->where('checkout_idempotency_key', $key)
            ->firstOrFail();
        $this->assertSame([$selectedItem->id, $giftComponent->id], $order->items()->orderBy('id')->pluck('id')->all());
        $this->assertSame([$gift->id], $order->gifts()->pluck('id')->all());
        $this->assertSame([$remainingItem->id], $cart->fresh()->items()->pluck('id')->all());
        $this->assertSame([], $cart->fresh()->gifts()->pluck('id')->all());
        $this->assertSame(200.0, (float) $cart->fresh()->final_total);
        $this->assertSame(2, (int) Inventory::sum('reserved_online_quantity'));

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/checkout', $payload)
            ->assertOk();
        $this->assertSame(1, Order::query()->where('checkout_idempotency_key', $key)->count());

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/checkout', array_replace($payload, [
                'cart_item_ids' => [$remainingItem->id],
                'cart_gift_ids' => [],
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Idempotency-Key уже использован для другого состава заказа');
    }

    public function test_explicit_empty_or_foreign_selection_is_rejected_without_changing_cart(): void
    {
        $context = $this->context();
        Sanctum::actingAs($context['user']);
        $cart = Order::factory()->cart()->create(['user_id' => $context['user']->id]);
        $item = $this->line($cart, $context['products'][0]);

        $this->postJson('/api/cart/quote', [
            'cart_item_ids' => [],
            'cart_gift_ids' => [],
        ])->assertUnprocessable();
        $this->postJson('/api/cart/quote', [
            'cart_item_ids' => [$item->id + 9999],
        ])->assertUnprocessable();

        $this->assertSame(Order::STATUS_CART, $cart->fresh()->status);
        $this->assertSame([$item->id], $cart->items()->pluck('id')->all());
        $this->assertSame(0, Order::query()->realOrders()->count());
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $brand = Brand::query()->create(['name' => 'Selective checkout']);
        $products = collect([
            ['Выбранный чай', 100],
            ['Оставшийся чай', 200],
            ['Компонент подарка', 300],
        ])->map(function (array $data) use ($brand): Product {
            return Product::query()->create([
                'brand_id' => $brand->id,
                'name' => $data[0],
                'price' => $data[1],
                'weight_grams' => 100,
                'stock_unit' => Product::STOCK_UNIT_PIECE,
                'sale_step' => 1,
                'price_unit_quantity' => 1,
                'is_available' => true,
                'total_quantity' => 20,
            ]);
        });
        $warehouse = Warehouse::query()->create([
            'name' => 'Склад выбранного заказа',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_WAREHOUSE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
            'is_delivery_hub' => true,
        ]);
        foreach ($products as $product) {
            Inventory::query()->create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 20,
            ]);
        }

        return [
            'user' => $user,
            'products' => $products->values(),
            'address' => AddressClient::query()->create([
                'user_id' => $user->id,
                'street' => 'Выборочная, 1',
                'city' => 'Москва',
                'postal_code' => '101000',
            ]),
            'delivery' => DeliveryMethod::query()->create([
                'name' => 'Выборочная доставка',
                'cost' => 0,
                'is_active' => true,
                'available_cities' => ['Москва'],
            ]),
        ];
    }

    private function line(Order $cart, Product $product)
    {
        return $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            ...$product->measurementSnapshot(),
            'unit_price' => $product->price,
            'final_unit_price' => $product->price,
            'total_price' => $product->price,
        ]);
    }
}
