<?php

namespace Tests\Feature\Warehouse;

use App\Models\AddressClient;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_availability_uses_both_reserves_and_only_enabled_locations(): void
    {
        $product = $this->createProduct();
        $warehouse = Warehouse::create([
            'name' => 'Склад',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_WAREHOUSE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
        ]);
        $store = Warehouse::create([
            'name' => 'Магазин',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_STORE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
        ]);
        $offlineWarehouse = Warehouse::create([
            'name' => 'Закрытый склад',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_WAREHOUSE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => false,
        ]);

        $warehouseInventory = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'reserved_online_quantity' => 3,
            'reserved_seller_quantity' => 2,
        ]);
        $storeInventory = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $store->id,
            'quantity' => 4,
            'reserved_online_quantity' => 2,
            'reserved_seller_quantity' => 3,
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $offlineWarehouse->id,
            'quantity' => 100,
        ]);

        $this->assertSame(5, $warehouseInventory->availableQuantity());
        $this->assertSame(0, $warehouseInventory->shortageQuantity());
        $this->assertSame(0, $storeInventory->availableQuantity());
        $this->assertSame(1, $storeInventory->shortageQuantity());
        $this->assertSame(
            5,
            Inventory::sumOnlineAvailable(
                Inventory::query()
                    ->where('product_id', $product->id)
                    ->onlineFulfillment()
            )
        );
        $this->assertTrue($warehouse->fresh()->is_online_fulfillment_enabled);
        $this->assertSame(Warehouse::TYPE_STORE, $store->fresh()->type);
    }

    public function test_manual_adjustment_requires_reason_and_creates_movement(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();
        $warehouse = Warehouse::create([
            'name' => 'Склад',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_WAREHOUSE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
        ]);
        $inventory = Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]);

        $service = app(WarehouseService::class);
        $service->adjustQuantity(
            $inventory,
            7,
            'Списали повреждённую упаковку',
            $user->id
        );

        $movement = InventoryMovement::sole();
        $this->assertSame(7, (int) $inventory->fresh()->quantity);
        $this->assertSame(InventoryMovement::TYPE_ADJUSTMENT, $movement->type);
        $this->assertSame(-3, $movement->physical_delta);
        $this->assertSame(10, $movement->physical_before);
        $this->assertSame(7, $movement->physical_after);
        $this->assertSame($user->id, $movement->actor_id);
        $this->assertSame('Списали повреждённую упаковку', $movement->reason);

        try {
            $service->adjustQuantity($inventory->fresh(), 6, '   ', $user->id);
            $this->fail('Ожидалась ошибка обязательной причины корректировки');
        } catch (DomainException) {
            // Остаток не должен измениться без объяснения причины.
        }

        $this->assertSame(7, (int) $inventory->fresh()->quantity);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_online_order_prefers_warehouse_before_store(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();
        $store = Warehouse::create([
            'name' => 'Магазин',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_STORE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Склад',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_WAREHOUSE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
        ]);

        foreach ([$store, $warehouse] as $location) {
            Inventory::create([
                'product_id' => $product->id,
                'warehouse_id' => $location->id,
                'quantity' => 10,
            ]);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
            'final_unit_price' => 100,
            'total_price' => 300,
        ]);

        $allocation = app(WarehouseService::class)->determineWarehousesForOrder(
            $order,
            new AddressClient(['city' => 'Москва'])
        );

        $this->assertCount(1, $allocation);
        $this->assertSame($warehouse->id, $allocation[0]['warehouse_id']);
        $this->assertSame(3, $allocation[0]['items'][0]['quantity']);
    }

    private function createProduct(): Product
    {
        $brand = Brand::firstOrCreate(['name' => 'Warehouse test brand']);

        return Product::create([
            'name' => 'Складской товар ' . uniqid(),
            'brand_id' => $brand->id,
            'price' => 100,
            'weight_grams' => 100,
            'is_available' => true,
            'total_quantity' => 0,
        ]);
    }
}
