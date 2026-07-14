<?php

namespace Tests\Feature\Checkout;

use App\Models\AddressClient;
use App\Models\Brand;
use App\Models\DeliveryMethod;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\WarehouseService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_and_cancellation_are_idempotent_and_restore_each_reserve_once(): void
    {
        $user = User::factory()->create();
        $brand = Brand::create(['name' => 'Test brand']);
        $product = $this->createProduct($brand, 'Ассам', 100);
        $address = AddressClient::create([
            'user_id' => $user->id,
            'street' => 'Тестовая, 1',
            'city' => 'Москва',
            'postal_code' => '101000',
        ]);
        $deliveryMethod = DeliveryMethod::create([
            'name' => 'Курьер',
            'cost' => 50,
            'is_active' => true,
            'available_cities' => ['Москва'],
        ]);

        $firstWarehouse = Warehouse::create([
            'name' => 'Склад 1',
            'city' => 'Москва',
            'is_active' => true,
        ]);
        $secondWarehouse = Warehouse::create([
            'name' => 'Склад 2',
            'city' => 'Москва',
            'is_active' => true,
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $firstWarehouse->id,
            'quantity' => 1,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $secondWarehouse->id,
            'quantity' => 2,
        ]);

        $cart = Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_CART,
            'products_total' => 300,
            'final_total' => 300,
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
            'promotion_discount_percent' => 0,
            'personal_discount_percent' => 0,
            'final_unit_price' => 100,
            'total_price' => 300,
        ]);
        $promotion = Discount::create([
            'name' => 'Минус 10 ₽ с единицы',
            'value' => 10,
            'value_type' => Discount::VALUE_FIXED,
            'type' => Discount::TYPE_PROMOTION,
            'is_active' => true,
            'is_global' => false,
            'usage_limit' => 3,
        ]);
        $promotion->products()->attach($product);

        $service = app(CheckoutService::class);
        $idempotencyKey = 'checkout-integrity-test-0001';

        $order = $service->checkout(
            $user->id,
            $address->id,
            $deliveryMethod->id,
            Order::PAYMENT_CARD,
            null,
            false,
            $idempotencyKey,
        );

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertNull($order->warehouse_id);
        $this->assertSame(320.0, (float) $order->final_total);
        $this->assertSame(3, (int) $promotion->fresh()->used_count);
        $this->assertSame(2, $order->partialOrders()->count());
        $this->assertSame(0, Inventory::sum('quantity'));

        $repeatedOrder = $service->checkout(
            $user->id,
            $address->id,
            $deliveryMethod->id,
            Order::PAYMENT_CARD,
            null,
            false,
            $idempotencyKey,
        );

        $this->assertSame($order->id, $repeatedOrder->id);
        $this->assertSame(1, Order::whereNull('parent_order_id')->realOrders()->count());
        $this->assertSame(0, Inventory::sum('quantity'));
        $this->assertSame(3, (int) $promotion->fresh()->used_count);

        $cancelledOrder = $service->cancelOrder($user->id, $order->id);

        $this->assertSame(Order::STATUS_CANCELLED, $cancelledOrder->status);
        $this->assertSame(3, Inventory::sum('quantity'));
        $this->assertSame(0, (int) $promotion->fresh()->used_count);
        $this->assertSame(
            2,
            Order::where('parent_order_id', $order->id)
                ->where('status', Order::STATUS_CANCELLED)
                ->count()
        );

        $service->cancelOrder($user->id, $order->id);

        $this->assertSame(3, Inventory::sum('quantity'));
        $this->assertSame(0, (int) $promotion->fresh()->used_count);
    }

    public function test_failed_multi_item_reservation_rolls_back_all_decrements(): void
    {
        $brand = Brand::create(['name' => 'Rollback brand']);
        $firstProduct = $this->createProduct($brand, 'Чай', 100);
        $secondProduct = $this->createProduct($brand, 'Конфеты', 200);
        $warehouse = Warehouse::create([
            'name' => 'Склад',
            'city' => 'Москва',
            'is_active' => true,
        ]);

        Inventory::create([
            'product_id' => $firstProduct->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
        ]);
        Inventory::create([
            'product_id' => $secondProduct->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
        ]);

        $allocation = [[
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $firstProduct->id, 'quantity' => 2],
                ['product_id' => $secondProduct->id, 'quantity' => 2],
            ],
        ]];

        try {
            DB::transaction(
                fn () => app(WarehouseService::class)->reserveStockByAllocation($allocation)
            );
            $this->fail('Ожидалась ошибка недостаточного остатка');
        } catch (DomainException) {
            // Транзакция должна отменить уже выполненное уменьшение первого товара.
        }

        $this->assertSame(
            5,
            Inventory::where('product_id', $firstProduct->id)->value('quantity')
        );
        $this->assertSame(
            1,
            Inventory::where('product_id', $secondProduct->id)->value('quantity')
        );
    }

    private function createProduct(Brand $brand, string $name, float $price): Product
    {
        $product = new Product();
        $product->name = $name;
        $product->brand_id = $brand->id;
        $product->price = $price;
        $product->weight_grams = 100;
        $product->is_available = true;
        $product->total_quantity = 10;
        $product->save();

        return $product;
    }
}
