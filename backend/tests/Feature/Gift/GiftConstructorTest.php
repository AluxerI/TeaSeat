<?php

namespace Tests\Feature\Gift;

use App\Models\Brand;
use App\Models\AddressClient;
use App\Models\GiftSizeProfile;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use App\Services\WarehouseService;
use Tests\TestCase;

class GiftConstructorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Warehouse $warehouse;
    private GiftSizeProfile $box;
    private GiftSizeProfile $itemSize;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->warehouse = Warehouse::factory()->create([
            'city' => 'Москва',
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
        ]);
        $this->box = GiftSizeProfile::query()->create([
            'code' => 'box-3x1',
            'name' => 'Коробка 3 × 1',
            'kind' => GiftSizeProfile::KIND_BOX,
            'width_cells' => 3,
            'height_cells' => 1,
            'default_markup_amount' => 50,
            'simple_constructor_enabled' => true,
            'simple_tea_count' => 2,
            'simple_sweet_count' => 1,
            'is_active' => true,
        ]);
        $this->itemSize = GiftSizeProfile::query()->create([
            'code' => 'item-1x1',
            'name' => 'Одна ячейка',
            'kind' => GiftSizeProfile::KIND_ITEM,
            'width_cells' => 1,
            'height_cells' => 1,
            'is_active' => true,
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_simple_constructor_is_a_facade_over_the_grid_and_markup_is_server_controlled(): void
    {
        [$teaA, $teaASize] = $this->constructorProduct('Чай A', ProductSize::ROLE_TEA);
        [$teaB, $teaBSize] = $this->constructorProduct('Чай B', ProductSize::ROLE_TEA);
        [$sweet, $sweetSize] = $this->constructorProduct('Конфета', ProductSize::ROLE_SWEET);

        $response = $this->postJson('/api/gift-constructor/simple/gifts', [
            'box_profile_id' => $this->box->id,
            'name' => 'Мой подарок',
            'tea_product_size_ids' => [$teaASize->id, $teaBSize->id],
            'sweet_product_size_ids' => [$sweetSize->id],
            'markup_amount' => 0,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Мой подарок')
            ->assertJsonPath('data.markup_amount', 50)
            ->assertJsonCount(3, 'data.items');

        $giftId = $response->json('data.id');
        $instanceId = (string) Str::uuid();
        $this->postJson('/api/cart/gifts', [
            'gift_id' => $giftId,
            'gift_version' => 1,
            'quantity' => 1,
            'client_instance_id' => $instanceId,
        ])->assertOk()
            ->assertJsonPath('data.products_total', 350)
            ->assertJsonPath('data.gift_markup_total', 50)
            ->assertJsonCount(1, 'data.gifts')
            ->assertJsonCount(3, 'data.gifts.0.items');

        $this->assertSame(3, collect([$teaA, $teaB, $sweet])->count());
    }

    public function test_simple_constructor_uses_box_counts_and_allows_duplicate_products(): void
    {
        $largeBox = GiftSizeProfile::query()->create([
            'code' => 'box-large-4x2',
            'name' => 'Большой подарок',
            'kind' => GiftSizeProfile::KIND_BOX,
            'width_cells' => 4,
            'height_cells' => 2,
            'default_markup_amount' => 100,
            'simple_constructor_enabled' => true,
            'simple_tea_count' => 5,
            'simple_sweet_count' => 2,
            'is_active' => true,
        ]);
        [, $teaSize] = $this->constructorProduct('Любимый чай', ProductSize::ROLE_TEA);
        [, $sweetSize] = $this->constructorProduct('Любимая конфета', ProductSize::ROLE_SWEET);

        $this->getJson('/api/gift-constructor/simple/options')
            ->assertOk()
            ->assertJsonFragment([
                'tea_count' => 5,
                'sweet_count' => 2,
                'total_items' => 7,
                'allow_duplicate_products' => true,
            ])
            ->assertJsonPath(
                'data.selection_rules.allow_duplicate_products',
                true
            );

        $payload = [
            'box_profile_id' => $largeBox->id,
            'name' => 'Большой подарок с повторами',
            'tea_product_size_ids' => array_fill(0, 5, $teaSize->id),
            'sweet_product_size_ids' => array_fill(0, 2, $sweetSize->id),
        ];
        $response = $this->postJson(
            '/api/gift-constructor/simple/gifts',
            $payload
        )->assertCreated()
            ->assertJsonCount(7, 'data.items')
            ->assertJsonPath('data.box.simple_requirements.tea_count', 5)
            ->assertJsonPath('data.box.simple_requirements.sweet_count', 2);

        $items = collect($response->json('data.items'));
        $this->assertSame(5, $items->filter(fn (array $item): bool =>
            (int) $item['product_size']['id'] === $teaSize->id
        )->count());
        $this->assertSame(2, $items->filter(fn (array $item): bool =>
            (int) $item['product_size']['id'] === $sweetSize->id
        )->count());

        $payload['tea_product_size_ids'] = array_fill(0, 4, $teaSize->id);
        $this->postJson('/api/gift-constructor/simple/gifts', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'gift_configuration_invalid');
    }

    public function test_box_catalog_returns_only_product_sizes_that_fit(): void
    {
        [, $regularSize] = $this->constructorProduct(
            'Подходящий чай',
            ProductSize::ROLE_TEA
        );

        $rotatableProfile = GiftSizeProfile::query()->create([
            'code' => 'item-1x2-rotatable',
            'name' => 'Поворачиваемый товар',
            'kind' => GiftSizeProfile::KIND_ITEM,
            'width_cells' => 1,
            'height_cells' => 2,
            'can_rotate' => true,
            'is_active' => true,
        ]);
        $rotatableSize = ProductSize::query()->create([
            'product_id' => $this->product('Поворачиваемый чай', 10)->id,
            'gift_size_profile_id' => $rotatableProfile->id,
            'label' => '1 × 2',
            'product_quantity' => 1,
            'constructor_role' => ProductSize::ROLE_TEA,
            'is_active' => true,
        ]);

        $oversizedProfile = GiftSizeProfile::query()->create([
            'code' => 'item-4x1-too-large',
            'name' => 'Слишком большой товар',
            'kind' => GiftSizeProfile::KIND_ITEM,
            'width_cells' => 4,
            'height_cells' => 1,
            'can_rotate' => false,
            'is_active' => true,
        ]);
        $oversizedSize = ProductSize::query()->create([
            'product_id' => $this->product('Большой чай', 10)->id,
            'gift_size_profile_id' => $oversizedProfile->id,
            'label' => '4 × 1',
            'product_quantity' => 1,
            'constructor_role' => ProductSize::ROLE_TEA,
            'is_active' => true,
        ]);

        $unavailableSize = ProductSize::query()->create([
            'product_id' => $this->product('Недоступный чай', 0)->id,
            'gift_size_profile_id' => $this->itemSize->id,
            'label' => 'Стандарт',
            'product_quantity' => 1,
            'constructor_role' => ProductSize::ROLE_TEA,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/gift-constructor/boxes/{$this->box->id}/products"
        )->assertOk()
            ->assertJsonPath('data.box.id', $this->box->id)
            ->assertJsonPath(
                'data.box.simple_requirements.allow_duplicate_products',
                true
            );

        $ids = collect($response->json('data.product_sizes'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $this->assertContains($regularSize->id, $ids);
        $this->assertContains($rotatableSize->id, $ids);
        $this->assertNotContains($oversizedSize->id, $ids);
        $this->assertNotContains($unavailableSize->id, $ids);

        $this->getJson(
            "/api/gift-constructor/boxes/{$this->itemSize->id}/products"
        )->assertNotFound();
    }

    public function test_advanced_layout_rejects_overlapping_items(): void
    {
        [, $sizeA] = $this->constructorProduct('Товар A', ProductSize::ROLE_GENERAL);
        [, $sizeB] = $this->constructorProduct('Товар B', ProductSize::ROLE_GENERAL);

        $this->postJson('/api/gift-constructor/advanced/validate-layout', [
            'box_profile_id' => $this->box->id,
            'items' => [
                $this->layoutItem($sizeA->id, 0),
                $this->layoutItem($sizeB->id, 0),
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'gift_configuration_invalid');
    }

    public function test_cart_aggregates_standalone_and_gift_component_stock(): void
    {
        [$teaA, $teaASize] = $this->constructorProduct('Редкий чай', ProductSize::ROLE_TEA, stock: 1);
        [, $teaBSize] = $this->constructorProduct('Чай B', ProductSize::ROLE_TEA);
        [, $sweetSize] = $this->constructorProduct('Конфета', ProductSize::ROLE_SWEET);
        $gift = $this->postJson('/api/gift-constructor/simple/gifts', [
            'box_profile_id' => $this->box->id,
            'name' => 'Подарок с редким чаем',
            'tea_product_size_ids' => [$teaASize->id, $teaBSize->id],
            'sweet_product_size_ids' => [$sweetSize->id],
        ])->assertCreated()->json('data');

        $this->postJson('/api/cart/gifts', [
            'gift_id' => $gift['id'],
            'gift_version' => $gift['version'],
            'quantity' => 1,
            'client_instance_id' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson('/api/cart/add', [
            'product_id' => $teaA->id,
            'quantity' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('order_products', [
            'product_id' => $teaA->id,
            'order_gift_id' => null,
        ]);
    }

    public function test_picker_replenishment_is_atomic_idempotent_and_does_not_consume_reserved_stock(): void
    {
        $picker = User::factory()->create();
        $picker->assignRole(User::ROLE_PICKER);
        $picker->warehouses()->attach($this->warehouse->id, ['is_active' => true]);
        Sanctum::actingAs($picker);

        $component = $this->product('Компонент', 10);
        $componentInventory = Inventory::query()
            ->where('product_id', $component->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();
        $componentInventory->update(['reserved_online_quantity' => 3]);
        $finished = $this->product('Готовый подарок', 0, Product::TYPE_PREASSEMBLED_GIFT);
        $key = (string) Str::uuid();
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'idempotency_key' => $key,
            'consumed_items' => [[
                'product_id' => $component->id,
                'quantity' => 7,
            ]],
        ];

        $this->postJson("/api/picker/assembled-gifts/{$finished->id}/replenish", $payload)
            ->assertOk()
            ->assertJsonPath('data.already_applied', false)
            ->assertJsonPath('data.new_quantity', 2);
        $this->postJson("/api/picker/assembled-gifts/{$finished->id}/replenish", $payload)
            ->assertOk()
            ->assertJsonPath('data.already_applied', true);

        $this->assertSame(3, (int) $componentInventory->fresh()->quantity);
        $this->assertSame(3, (int) $componentInventory->fresh()->reserved_online_quantity);
        $this->assertSame(2, InventoryMovement::query()
            ->whereIn('type', [
                InventoryMovement::TYPE_GIFT_ASSEMBLY_CONSUME,
                InventoryMovement::TYPE_GIFT_ASSEMBLY_PRODUCE,
            ])->count());
    }

    public function test_duplicate_sku_lines_are_allocated_and_reserved_as_one_stock_request(): void
    {
        $product = $this->product('Одинаковый SKU', 2);
        $order = Order::query()->create([
            'user_id' => $this->user->id,
            'status' => Order::STATUS_PENDING,
            'products_total' => 200,
            'final_total' => 200,
        ]);
        foreach ([1, 1] as $quantity) {
            $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => 100,
                'final_unit_price' => 100,
                'total_price' => 100,
            ]);
        }

        $service = app(WarehouseService::class);
        $allocation = $service->determineWarehousesForOrder(
            $order,
            new AddressClient(['city' => 'Москва'])
        );
        $parts = $service->createPartialOrders(
            $order,
            $allocation,
            new AddressClient(['city' => 'Москва'])
        );
        $service->reserveOnlineStockForOrders($parts, $this->user->id);

        $inventory = Inventory::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
        $this->assertSame(2, (int) $inventory->reserved_online_quantity);
        $this->assertSame(1, InventoryMovement::query()
            ->where('type', InventoryMovement::TYPE_ONLINE_RESERVE)
            ->where('inventory_id', $inventory->id)
            ->count());
    }

    private function constructorProduct(string $name, string $role, int $stock = 10): array
    {
        $product = $this->product($name, $stock);
        $size = ProductSize::query()->create([
            'product_id' => $product->id,
            'gift_size_profile_id' => $this->itemSize->id,
            'label' => 'Стандарт',
            'product_quantity' => 1,
            'constructor_role' => $role,
            'is_active' => true,
        ]);
        return [$product, $size];
    }

    private function product(
        string $name,
        int $stock,
        string $type = Product::TYPE_REGULAR
    ): Product {
        $brand = Brand::query()->firstOrCreate(['name' => 'Gift test brand']);
        $product = Product::query()->create([
            'name' => $name . ' ' . Str::random(6),
            'brand_id' => $brand->id,
            'price' => 100,
            'product_type' => $type,
            'is_individual_sale_enabled' => true,
            'stock_unit' => Product::STOCK_UNIT_PIECE,
            'sale_step' => 1,
            'price_unit_quantity' => 1,
            'is_available' => $stock > 0,
        ]);
        Inventory::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => $stock,
        ]);
        return $product->fresh();
    }

    private function layoutItem(int $productSizeId, int $x): array
    {
        return [
            'client_item_id' => (string) Str::uuid(),
            'product_size_id' => $productSizeId,
            'position_x' => $x,
            'position_y' => 0,
            'is_rotated' => false,
        ];
    }
}
