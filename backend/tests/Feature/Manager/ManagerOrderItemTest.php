<?php

namespace Tests\Feature\Manager;

use App\Models\AddressClient;
use App\Models\Brand;
use App\Models\DeliveryMethod;
use App\Models\Discount;
use App\Models\Gift;
use App\Models\GiftSizeProfile;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ManagerOrderAdjustment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\GiftCartService;
use App\Services\StaffAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagerOrderItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_replacement_uses_current_site_price_and_rebuilds_reserve_once(): void
    {
        $context = $this->context();
        $oldProduct = $this->product($context['brand'], 'Старый чай', 100);
        $newProduct = $this->product($context['brand'], 'Новый чай', 200);
        $oldInventory = $this->inventory($context['warehouse'], $oldProduct, 10);
        $newInventory = $this->inventory($context['warehouse'], $newProduct, 10);
        $promotion = Discount::query()->create([
            'name' => 'Актуальная акция нового чая',
            'value' => 25,
            'value_type' => Discount::VALUE_FIXED,
            'type' => Discount::TYPE_PROMOTION,
            'is_active' => true,
            'is_global' => false,
            'usage_limit' => 10,
            'created_by' => $context['manager']->id,
        ]);
        $promotion->products()->attach($newProduct->id);
        $order = $this->checkout($context, $oldProduct, 2);
        $item = $order->items()->sole();
        $operationId = (string) Str::uuid();

        Sanctum::actingAs($context['manager']);
        $payload = [
            'operation_id' => $operationId,
            'product_id' => $newProduct->id,
            'quantity' => 2,
            'reason' => 'Клиент согласовал замену сорта',
        ];
        $this->postJson(
            "/api/manager/orders/{$order->id}/items/{$item->id}/replace",
            $payload
        )
            ->assertOk()
            ->assertJsonPath('already_applied', false)
            ->assertJsonPath('data.actions.can_modify_items', true)
            ->assertJsonPath('data.items.0.product.id', $newProduct->id)
            ->assertJsonPath('data.items.0.prices.unit_price', 200)
            ->assertJsonPath('data.items.0.prices.total_price', 350)
            ->assertJsonPath('data.totals.final_total', 350)
            ->assertJsonPath(
                'adjustment.action',
                ManagerOrderAdjustment::ACTION_PRODUCT_REPLACED
            );

        $this->assertSame(0, (int) $oldInventory->fresh()->reserved_online_quantity);
        $this->assertSame(2, (int) $newInventory->fresh()->reserved_online_quantity);
        $this->assertSame(2, (int) $promotion->fresh()->used_count);
        $this->assertSame(1, $order->partialOrders()->count());
        $this->assertSame(
            1,
            Order::onlyTrashed()->where('parent_order_id', $order->id)->count()
        );
        $this->assertSame(1, ManagerOrderAdjustment::count());

        $this->postJson(
            "/api/manager/orders/{$order->id}/items/{$item->id}/replace",
            $payload
        )
            ->assertOk()
            ->assertJsonPath('already_applied', true);

        $this->assertSame(2, (int) $newInventory->fresh()->reserved_online_quantity);
        $this->assertSame(2, (int) $promotion->fresh()->used_count);
        $this->assertSame(1, ManagerOrderAdjustment::count());
    }

    public function test_existing_line_increase_keeps_checkout_price(): void
    {
        $context = $this->context();
        $product = $this->product($context['brand'], 'Чай по старой цене', 100);
        $inventory = $this->inventory($context['warehouse'], $product, 10);
        $order = $this->checkout($context, $product, 1);
        $item = $order->items()->sole();
        $product->update(['price' => 999]);

        Sanctum::actingAs($context['manager']);
        $this->patchJson(
            "/api/manager/orders/{$order->id}/items/{$item->id}",
            [
                'operation_id' => (string) Str::uuid(),
                'quantity' => 3,
                'reason' => 'Покупатель попросил добавить ещё две упаковки',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.prices.unit_price', 100)
            ->assertJsonPath('data.items.0.prices.total_price', 300)
            ->assertJsonPath('data.totals.final_total', 300);

        $this->assertSame(3, (int) $inventory->fresh()->reserved_online_quantity);
    }

    public function test_failed_replacement_rolls_back_line_reserve_and_audit(): void
    {
        $context = $this->context();
        $oldProduct = $this->product($context['brand'], 'Доступный чай', 100);
        $scarceProduct = $this->product($context['brand'], 'Редкий чай', 300);
        $oldInventory = $this->inventory($context['warehouse'], $oldProduct, 10);
        $scarceInventory = $this->inventory($context['warehouse'], $scarceProduct, 1);
        $order = $this->checkout($context, $oldProduct, 2);
        $item = $order->items()->sole();
        $partId = $order->partialOrders()->sole()->id;

        Sanctum::actingAs($context['manager']);
        $this->postJson(
            "/api/manager/orders/{$order->id}/items/{$item->id}/replace",
            [
                'operation_id' => (string) Str::uuid(),
                'product_id' => $scarceProduct->id,
                'quantity' => 2,
                'reason' => 'Проверка атомарного отката при нехватке',
            ]
        )
            ->assertConflict()
            ->assertJsonPath('code', 'manager_order_edit_rejected');

        $this->assertSame($oldProduct->id, $item->fresh()->product_id);
        $this->assertSame(2, (int) $oldInventory->fresh()->reserved_online_quantity);
        $this->assertSame(0, (int) $scarceInventory->fresh()->reserved_online_quantity);
        $this->assertDatabaseHas('orders', [
            'id' => $partId,
            'deleted_at' => null,
        ]);
        $this->assertSame(0, ManagerOrderAdjustment::count());
        $this->assertSame(
            0,
            InventoryMovement::query()
                ->where('type', InventoryMovement::TYPE_ONLINE_RELEASE)
                ->count()
        );
    }

    public function test_paid_order_cannot_be_edited(): void
    {
        $context = $this->context();
        $product = $this->product($context['brand'], 'Обычный чай', 100);
        $this->inventory($context['warehouse'], $product, 10);
        $order = $this->checkout($context, $product, 1);
        $item = $order->items()->sole();
        $order->update(['paid_at' => now()]);

        Sanctum::actingAs($context['manager']);
        $this->patchJson(
            "/api/manager/orders/{$order->id}/items/{$item->id}",
            [
                'operation_id' => (string) Str::uuid(),
                'quantity' => 2,
                'reason' => 'Оплаченный заказ должен быть защищён',
            ]
        )->assertConflict();

        $this->assertSame(1, (int) $item->fresh()->quantity);
        $this->assertSame(0, ManagerOrderAdjustment::count());
    }

    public function test_gift_is_replaced_as_a_whole_with_current_component_price(): void
    {
        $context = $this->context();
        $oldComponent = $this->product($context['brand'], 'Старый состав', 100);
        $newComponent = $this->product($context['brand'], 'Новый состав', 180);
        $oldInventory = $this->inventory($context['warehouse'], $oldComponent, 10);
        $newInventory = $this->inventory($context['warehouse'], $newComponent, 10);
        $oldGift = $this->configuredGift(
            $context['customer'],
            $oldComponent,
            'Старый набор',
            10
        );
        $newGift = $this->configuredGift(
            $context['customer'],
            $newComponent,
            'Новый набор',
            20
        );
        Order::query()->create([
            'user_id' => $context['customer']->id,
            'status' => Order::STATUS_CART,
        ]);
        app(GiftCartService::class)->add(
            $context['customer'],
            $oldGift->id,
            1,
            1,
            (string) Str::uuid(),
            'Москва'
        );
        $order = app(CheckoutService::class)->checkout(
            $context['customer']->id,
            $context['address']->id,
            $context['deliveryMethod']->id,
            Order::PAYMENT_CASH,
            null,
            false,
            (string) Str::uuid()
        );
        $orderGift = $order->gifts()->sole();

        Sanctum::actingAs($context['manager']);
        $this->postJson(
            "/api/manager/orders/{$order->id}/gifts/{$orderGift->id}/replace",
            [
                'operation_id' => (string) Str::uuid(),
                'gift_id' => $newGift->id,
                'gift_version' => 1,
                'quantity' => 1,
                'reason' => 'Клиент выбрал новый набор вместо ошибочного',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'adjustment.action',
                ManagerOrderAdjustment::ACTION_GIFT_REPLACED
            )
            ->assertJsonPath('data.gifts.0.gift_id', $newGift->id)
            ->assertJsonPath('data.gifts.0.prices.total_price', 200)
            ->assertJsonPath('data.totals.final_total', 200);

        $this->assertSame(0, (int) $oldInventory->fresh()->reserved_online_quantity);
        $this->assertSame(1, (int) $newInventory->fresh()->reserved_online_quantity);
        $this->assertDatabaseMissing('order_gifts', ['id' => $orderGift->id]);
    }

    private function context(): array
    {
        $manager = User::factory()->create();
        $manager->assignRole(User::ROLE_MANAGER);
        $customer = User::factory()->create();
        $brand = Brand::query()->create(['name' => 'Manager item test']);
        $warehouse = Warehouse::query()->create([
            'name' => 'Главный склад',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_WAREHOUSE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
            'is_delivery_hub' => true,
        ]);
        app(StaffAccessService::class)->syncActiveLocations(
            $manager,
            [$warehouse->id]
        );
        $address = AddressClient::query()->create([
            'user_id' => $customer->id,
            'street' => 'Тестовая, 1',
            'city' => 'Москва',
            'postal_code' => '101000',
        ]);
        $deliveryMethod = DeliveryMethod::query()->create([
            'name' => 'Курьер',
            'cost' => 0,
            'is_active' => true,
            'available_cities' => ['Москва'],
        ]);

        return compact(
            'manager',
            'customer',
            'brand',
            'warehouse',
            'address',
            'deliveryMethod'
        );
    }

    private function product(
        Brand $brand,
        string $name,
        float $price
    ): Product {
        return Product::query()->create([
            'name' => $name,
            'brand_id' => $brand->id,
            'price' => $price,
            'weight_grams' => 100,
            'is_available' => true,
            'total_quantity' => 10,
        ]);
    }

    private function inventory(
        Warehouse $warehouse,
        Product $product,
        int $quantity
    ): Inventory {
        return Inventory::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_online_quantity' => 0,
            'reserved_seller_quantity' => 0,
        ]);
    }

    private function configuredGift(
        User $customer,
        Product $component,
        string $name,
        float $markup
    ): Gift {
        $itemProfile = GiftSizeProfile::query()->create([
            'code' => 'item-' . Str::lower(Str::random(8)),
            'name' => $name . ' — ячейка',
            'kind' => GiftSizeProfile::KIND_ITEM,
            'width_cells' => 1,
            'height_cells' => 1,
            'can_rotate' => true,
            'default_markup_amount' => 0,
            'simple_constructor_enabled' => false,
            'is_active' => true,
        ]);
        $boxProfile = GiftSizeProfile::query()->create([
            'code' => 'box-' . Str::lower(Str::random(8)),
            'name' => $name . ' — коробка',
            'kind' => GiftSizeProfile::KIND_BOX,
            'width_cells' => 1,
            'height_cells' => 1,
            'can_rotate' => true,
            'default_markup_amount' => $markup,
            'simple_constructor_enabled' => false,
            'is_active' => true,
        ]);
        $size = ProductSize::query()->create([
            'product_id' => $component->id,
            'gift_size_profile_id' => $itemProfile->id,
            'label' => 'Стандарт',
            'product_quantity' => 1,
            'constructor_role' => ProductSize::ROLE_GENERAL,
            'is_active' => true,
        ]);
        $gift = Gift::query()->create([
            'user_id' => $customer->id,
            'gift_size_profile_id' => $boxProfile->id,
            'name' => $name,
            'status' => Gift::STATUS_ACTIVE,
            'visibility' => Gift::VISIBILITY_PRIVATE,
            'markup_amount' => $markup,
            'version' => 1,
            'layout_snapshot' => [
                'box_profile_id' => $boxProfile->id,
                'items' => [],
            ],
        ]);
        $gift->items()->create([
            'product_size_id' => $size->id,
            'client_item_id' => (string) Str::uuid(),
            'position_x' => 0,
            'position_y' => 0,
            'is_rotated' => false,
            'sort_order' => 0,
        ]);

        return $gift;
    }

    private function checkout(
        array $context,
        Product $product,
        int $quantity
    ): Order {
        $cart = Order::query()->create([
            'user_id' => $context['customer']->id,
            'status' => Order::STATUS_CART,
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'stock_unit' => $product->stockUnit(),
            'sale_step' => $product->saleStep(),
            'price_unit_quantity' => $product->priceUnitQuantity(),
            'unit_price' => $product->price,
            'final_unit_price' => $product->price,
            'total_price' => $product->baseTotalForQuantity($quantity),
        ]);

        return app(CheckoutService::class)->checkout(
            $context['customer']->id,
            $context['address']->id,
            $context['deliveryMethod']->id,
            Order::PAYMENT_CASH,
            null,
            false,
            (string) Str::uuid()
        );
    }
}
