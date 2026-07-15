<?php

namespace Tests\Feature\Fulfillment;

use App\Models\DeliveryMethod;
use App\Models\FulfillmentIssue;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StaffAccessService;
use App\Services\OrderManagementService;
use Database\Seeders\RolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PickingDeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_picker_is_limited_to_assigned_warehouse_and_stock_is_committed_when_packed(): void
    {
        $hub = $this->warehouse('Основной хаб', true);
        $foreign = $this->warehouse('Чужой склад');
        $picker = $this->staff(User::ROLE_PICKER, [$hub]);
        $otherPicker = $this->staff(User::ROLE_PICKER, [$foreign]);
        $graph = $this->orderGraph($hub, [[$hub, 2]]);
        $part = $graph['parts'][0];

        try {
            app(OrderManagementService::class)->markAsShipped(
                $graph['main'],
                $picker->id
            );
            $this->fail('Ожидался запрет отправки до упаковки');
        } catch (DomainException) {
            // Ручная смена статуса не должна обходить сборку.
        }

        Sanctum::actingAs($otherPicker);
        $this->getJson('/api/picker/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->postJson("/api/picker/orders/{$part->id}/take")
            ->assertForbidden();

        Sanctum::actingAs($picker);
        $this->postJson("/api/picker/orders/{$part->id}/take")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_PROCESSING)
            ->assertJsonPath('data.picker.id', $picker->id);
        $this->postJson("/api/picker/orders/{$part->id}/release")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CONFIRMED);
        $this->postJson("/api/picker/orders/{$part->id}/take")->assertOk();

        $this->postJson("/api/picker/orders/{$part->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DELIVERED);

        $inventory = $graph['inventories'][$hub->id]->fresh();
        $this->assertSame(8, (int) $inventory->quantity);
        $this->assertSame(0, (int) $inventory->reserved_online_quantity);
        $this->assertNotNull($part->fresh()->stock_committed_at);
        $this->assertSame(
            Order::STATUS_READY_FOR_DELIVERY,
            $graph['main']->fresh()->status
        );
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('type', InventoryMovement::TYPE_ONLINE_SALE)
                ->count()
        );

        // Повторное завершение не списывает товар второй раз.
        $this->postJson("/api/picker/orders/{$part->id}/complete")->assertOk();
        $this->assertSame(8, (int) $inventory->fresh()->quantity);
    }

    public function test_picker_shortage_creates_waiting_manager_issue_without_stock_commit(): void
    {
        $hub = $this->warehouse('Хаб', true);
        $picker = $this->staff(User::ROLE_PICKER, [$hub]);
        $manager = $this->staff(User::ROLE_MANAGER, [$hub]);
        $graph = $this->orderGraph($hub, [[$hub, 2]], physicalQuantity: 1);
        $part = $graph['parts'][0];

        Sanctum::actingAs($picker);
        $this->postJson("/api/picker/orders/{$part->id}/take")->assertOk();
        $this->postJson("/api/picker/orders/{$part->id}/shortage", [
            'product_id' => $graph['product']->id,
            'shortage_quantity' => 1,
            'comment' => 'На полке не хватает одной упаковки',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_MANAGER_REVIEW)
            ->assertJsonPath(
                'fulfillment_issue.reason',
                FulfillmentIssue::REASON_PHYSICAL_STOCK_DISCREPANCY
            )
            ->assertJsonPath(
                'fulfillment_issue.status',
                FulfillmentIssue::STATUS_WAITING
            );

        $this->assertSame(Order::STATUS_MANAGER_REVIEW, $graph['main']->fresh()->status);
        $this->assertNull($part->fresh()->stock_committed_at);
        $this->assertSame(1, (int) $graph['inventories'][$hub->id]->fresh()->quantity);
        $this->assertDatabaseHas('fulfillment_issues', [
            'source_order_id' => $part->id,
            'product_id' => $graph['product']->id,
            'manager_id' => null,
            'status' => FulfillmentIssue::STATUS_WAITING,
        ]);

        Sanctum::actingAs($manager);
        $this->getJson('/api/manager/fulfillment-issues')
            ->assertOk()
            ->assertJsonPath('data.0.source_order.reported_by.id', $picker->id)
            ->assertJsonPath(
                'data.0.source_order.customer.id',
                $graph['main']->user_id
            );
    }

    public function test_multi_warehouse_parts_are_received_and_consolidated_before_customer_delivery(): void
    {
        $hub = $this->warehouse('Хаб', true);
        $remote = $this->warehouse('Удалённый склад');
        $picker = $this->staff(User::ROLE_PICKER, [$hub, $remote]);
        $courier = $this->staff(User::ROLE_COURIER, [$hub, $remote]);
        $otherCourier = $this->staff(User::ROLE_COURIER, [$remote]);
        $graph = $this->orderGraph($hub, [
            [$hub, 1],
            [$remote, 2],
        ]);
        [$localPart, $remotePart] = $graph['parts'];

        Sanctum::actingAs($picker);
        foreach ([$localPart, $remotePart] as $part) {
            $this->postJson("/api/picker/orders/{$part->id}/take")->assertOk();
            $this->postJson("/api/picker/orders/{$part->id}/complete")->assertOk();
        }

        $this->assertSame(Order::STATUS_DELIVERED, $localPart->fresh()->status);
        $this->assertSame(
            Order::STATUS_READY_FOR_DELIVERY,
            $remotePart->fresh()->status
        );
        $this->assertSame(Order::STATUS_CONFIRMED, $graph['main']->fresh()->status);
        $this->assertSame(9, (int) $graph['inventories'][$hub->id]->fresh()->quantity);
        $this->assertSame(8, (int) $graph['inventories'][$remote->id]->fresh()->quantity);

        Sanctum::actingAs($courier);
        $this->getJson('/api/courier/deliveries?delivery_kind=transfer')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $remotePart->id);
        $this->postJson("/api/courier/deliveries/{$remotePart->id}/claim")
            ->assertOk();

        Sanctum::actingAs($otherCourier);
        $this->postJson("/api/courier/deliveries/{$remotePart->id}/claim")
            ->assertStatus(409);

        Sanctum::actingAs($courier);
        $this->postJson("/api/courier/deliveries/{$remotePart->id}/start")
            ->assertOk();
        $this->postJson("/api/courier/deliveries/{$remotePart->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_AWAITING_RECEIPT);

        $this->assertSame(Order::STATUS_CONFIRMED, $graph['main']->fresh()->status);

        Sanctum::actingAs($picker);
        $this->getJson('/api/picker/incoming-transfers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $remotePart->id);
        $this->postJson("/api/picker/incoming-transfers/{$remotePart->id}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DELIVERED);

        $this->getJson('/api/picker/orders?job_type=consolidation')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $graph['main']->id);
        $this->postJson("/api/picker/orders/{$graph['main']->id}/take")
            ->assertOk();
        $this->postJson("/api/picker/orders/{$graph['main']->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_READY_FOR_DELIVERY);

        Sanctum::actingAs($courier);
        $claim = $this->postJson(
            "/api/courier/deliveries/{$graph['main']->id}/claim"
        )->assertOk();
        $this->assertEquals(
            300.0,
            $claim->json('data.payment.amount_to_collect')
        );
        $this->postJson("/api/courier/deliveries/{$graph['main']->id}/start")
            ->assertOk();
        $this->postJson("/api/courier/deliveries/{$graph['main']->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DELIVERED);
        $this->postJson("/api/courier/deliveries/{$graph['main']->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_DELIVERED);

        $this->assertSame(9, (int) $graph['inventories'][$hub->id]->fresh()->quantity);
        $this->assertSame(8, (int) $graph['inventories'][$remote->id]->fresh()->quantity);
    }

    public function test_manager_can_assign_courier_and_return_safe_package_to_reserved_stock(): void
    {
        $hub = $this->warehouse('Хаб', true);
        $picker = $this->staff(User::ROLE_PICKER, [$hub]);
        $courier = $this->staff(User::ROLE_COURIER, [$hub]);
        $manager = $this->staff(User::ROLE_MANAGER, [$hub]);
        $graph = $this->orderGraph($hub, [[$hub, 2]]);
        $part = $graph['parts'][0];

        Sanctum::actingAs($picker);
        $this->postJson("/api/picker/orders/{$part->id}/take")->assertOk();
        $this->postJson("/api/picker/orders/{$part->id}/complete")->assertOk();

        Sanctum::actingAs($manager);
        $this->postJson(
            "/api/manager/deliveries/{$graph['main']->id}/assign-courier",
            ['courier_id' => $courier->id]
        )
            ->assertOk()
            ->assertJsonPath('data.courier.id', $courier->id);

        $this->postJson(
            "/api/manager/orders/{$graph['main']->id}/return-to-stock",
            ['reason' => 'Обнаружена ошибка упаковки']
        )
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CONFIRMED);

        $inventory = $graph['inventories'][$hub->id]->fresh();
        $this->assertSame(10, (int) $inventory->quantity);
        $this->assertSame(2, (int) $inventory->reserved_online_quantity);
        $this->assertSame(Order::STATUS_CONFIRMED, $part->fresh()->status);
        $this->assertNull($part->fresh()->courier_id);
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('type', InventoryMovement::TYPE_ONLINE_RETURN)
                ->count()
        );

        Sanctum::actingAs($picker);
        $this->postJson("/api/picker/orders/{$part->id}/take")->assertOk();
        $this->postJson("/api/picker/orders/{$part->id}/complete")->assertOk();
        $this->assertSame(8, (int) $inventory->fresh()->quantity);
        $this->assertSame(
            2,
            InventoryMovement::query()
                ->where('type', InventoryMovement::TYPE_ONLINE_SALE)
                ->count()
        );

        Sanctum::actingAs($manager);
        $this->postJson(
            "/api/manager/deliveries/{$graph['main']->id}/assign-courier",
            ['courier_id' => $courier->id]
        )->assertOk();
        Sanctum::actingAs($courier);
        $this->postJson("/api/courier/deliveries/{$graph['main']->id}/start")
            ->assertOk();
        Sanctum::actingAs($manager);
        $this->postJson(
            "/api/manager/orders/{$graph['main']->id}/return-to-stock",
            ['reason' => 'Курьер уже забрал упаковку']
        )->assertStatus(409);
        $this->assertSame(8, (int) $inventory->fresh()->quantity);
    }

    public function test_external_delivery_is_handed_over_by_manager_not_internal_courier(): void
    {
        $hub = $this->warehouse('Хаб', true);
        $picker = $this->staff(User::ROLE_PICKER, [$hub]);
        $courier = $this->staff(User::ROLE_COURIER, [$hub]);
        $manager = $this->staff(User::ROLE_MANAGER, [$hub]);
        $graph = $this->orderGraph(
            $hub,
            [[$hub, 1]],
            deliveryType: DeliveryMethod::TYPE_EXTERNAL
        );
        $part = $graph['parts'][0];

        Sanctum::actingAs($picker);
        $this->postJson("/api/picker/orders/{$part->id}/take")->assertOk();
        $this->postJson("/api/picker/orders/{$part->id}/complete")->assertOk();

        Sanctum::actingAs($courier);
        $this->getJson('/api/courier/deliveries')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $service = app(OrderManagementService::class);
        $shipped = $service->markAsShipped($graph['main']->fresh(), $manager->id);
        $this->assertSame(Order::STATUS_SHIPPED, $shipped->status);
        $delivered = $service->markAsDelivered($shipped, $manager->id);
        $this->assertSame(Order::STATUS_DELIVERED, $delivered->status);
    }

    private function warehouse(string $name, bool $hub = false): Warehouse
    {
        return Warehouse::factory()->create([
            'name' => $name,
            'city' => 'Тула',
            'is_delivery_hub' => $hub,
        ]);
    }

    /** @param array<int, Warehouse> $warehouses */
    private function staff(string $role, array $warehouses): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        app(StaffAccessService::class)->syncActiveLocations(
            $user,
            collect($warehouses)->pluck('id')->all()
        );

        return $user;
    }

    /**
     * @param array<int, array{0:Warehouse,1:int}> $allocations
     * @return array{main:Order,parts:array<int,Order>,product:Product,inventories:array<int,Inventory>}
     */
    private function orderGraph(
        Warehouse $hub,
        array $allocations,
        int $physicalQuantity = 10,
        string $deliveryType = DeliveryMethod::TYPE_COURIER
    ): array {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $method = DeliveryMethod::query()->create([
            'name' => 'Курьерская доставка',
            'cost' => 0,
            'is_active' => true,
            'type' => $deliveryType,
        ]);
        $totalQuantity = collect($allocations)->sum(fn (array $item): int => $item[1]);
        $main = Order::factory()->create([
            'user_id' => $customer->id,
            'sales_channel' => Order::SALES_CHANNEL_ONLINE,
            'status' => Order::STATUS_CONFIRMED,
            'warehouse_id' => $hub->id,
            'delivery_method_id' => $method->id,
            'payment_method' => Order::PAYMENT_CASH,
            'products_total' => $totalQuantity * 100,
            'final_total' => $totalQuantity * 100,
            'stock_reserved_at' => now(),
            'confirmed_at' => now(),
        ]);
        $this->addItem($main, $product, $totalQuantity);
        $main->update([
            'products_total' => $totalQuantity * 100,
            'final_total' => $totalQuantity * 100,
        ]);

        $parts = [];
        $inventories = [];
        foreach ($allocations as [$warehouse, $quantity]) {
            $part = Order::factory()->create([
                'user_id' => $customer->id,
                'sales_channel' => Order::SALES_CHANNEL_ONLINE,
                'status' => Order::STATUS_CONFIRMED,
                'parent_order_id' => $main->id,
                'warehouse_id' => $warehouse->id,
                'destination_warehouse_id' => $hub->id,
                'delivery_method_id' => $method->id,
                'payment_method' => Order::PAYMENT_CASH,
                'stock_reserved_at' => now(),
                'confirmed_at' => now(),
            ]);
            $this->addItem($part, $product, $quantity);
            $parts[] = $part;
            $inventories[$warehouse->id] = Inventory::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => $physicalQuantity,
                'reserved_online_quantity' => $quantity,
                'reserved_seller_quantity' => 0,
            ]);
        }

        return compact('main', 'parts', 'product', 'inventories');
    }

    private function addItem(Order $order, Product $product, int $quantity): void
    {
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'stock_unit' => $product->stockUnit(),
            'sale_step' => $product->saleStep(),
            'price_unit_quantity' => $product->priceUnitQuantity(),
            'unit_price' => 100,
            'final_unit_price' => 100,
            'total_price' => $quantity * 100,
        ]);
    }
}
