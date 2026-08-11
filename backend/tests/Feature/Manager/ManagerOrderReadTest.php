<?php

namespace Tests\Feature\Manager;

use App\Models\FulfillmentIssue;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StaffAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagerOrderReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manager_sees_only_root_orders_touching_assigned_locations(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $admin = $this->staff(User::ROLE_ADMIN);
        $customer = $this->staff(User::ROLE_USER);
        $assigned = Warehouse::factory()->create();
        $foreign = Warehouse::factory()->create();
        $this->assign($manager, [$assigned]);

        $direct = Order::factory()->pending()->create([
            'warehouse_id' => $assigned->id,
        ]);
        $multiWarehouse = Order::factory()->pending()->create([
            'warehouse_id' => null,
        ]);
        $assignedPart = Order::factory()->pending()->create([
            'parent_order_id' => $multiWarehouse->id,
            'warehouse_id' => $assigned->id,
        ]);
        Order::factory()->pending()->create([
            'parent_order_id' => $multiWarehouse->id,
            'warehouse_id' => $foreign->id,
        ]);
        $foreignOrder = Order::factory()->pending()->create([
            'warehouse_id' => $foreign->id,
        ]);
        Order::factory()->cart()->create([
            'warehouse_id' => $assigned->id,
        ]);

        Sanctum::actingAs($manager);
        $response = $this->getJson('/api/manager/orders?per_page=100')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing(
            [$direct->id, $multiWarehouse->id],
            $ids
        );
        $this->assertNotContains($assignedPart->id, $ids);
        $this->getJson("/api/manager/orders/{$foreignOrder->id}")
            ->assertNotFound();
        $this->getJson('/api/admin/orders')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->getJson('/api/manager/orders?per_page=100')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        Sanctum::actingAs($customer);
        $this->getJson('/api/manager/orders')->assertForbidden();
    }

    public function test_manager_gets_full_multi_warehouse_card_with_read_scope(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $customer = User::factory()->create([
            'name' => 'Покупатель заказа',
            'email' => 'customer@example.test',
            'phone' => '+79990000001',
        ]);
        $assigned = Warehouse::factory()->create(['name' => 'Склад менеджера']);
        $foreign = Warehouse::factory()->create(['name' => 'Другой склад']);
        $this->assign($manager, [$assigned]);

        $order = Order::factory()->pending()->create([
            'user_id' => $customer->id,
            'warehouse_id' => null,
            'status' => Order::STATUS_MANAGER_REVIEW,
            'contact_name' => 'Получатель',
            'contact_phone' => '+79990000002',
            'internal_notes' => 'Нужна проверка менеджера',
        ]);
        $assignedPart = Order::factory()->pending()->create([
            'user_id' => $customer->id,
            'parent_order_id' => $order->id,
            'warehouse_id' => $assigned->id,
            'stock_reserved_at' => now(),
        ]);
        $foreignPart = Order::factory()->pending()->create([
            'user_id' => $customer->id,
            'parent_order_id' => $order->id,
            'warehouse_id' => $foreign->id,
            'stock_reserved_at' => now(),
        ]);
        $assignedProduct = Product::factory()->create(['name' => 'Чай менеджера']);
        $foreignProduct = Product::factory()->create(['name' => 'Чужая сладость']);
        $this->item($assignedPart, $assignedProduct, 2);
        $this->item($foreignPart, $foreignProduct, 3);
        $assignedIssue = $this->issue($assignedPart, $assignedProduct, $assigned);
        $foreignIssue = $this->issue($foreignPart, $foreignProduct, $foreign);
        $this->movement($assignedPart, $assignedProduct, $assigned, $manager);
        $this->movement($foreignPart, $foreignProduct, $foreign, $manager);
        $order->statusHistory()->create([
            'from_status' => Order::STATUS_CONFIRMED,
            'to_status' => Order::STATUS_MANAGER_REVIEW,
            'changed_by' => $manager->id,
            'notes' => 'Передано менеджеру',
        ]);

        Sanctum::actingAs($manager);
        $response = $this->getJson("/api/manager/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer.name', 'Получатель')
            ->assertJsonPath('data.customer.phone', '+79990000002')
            ->assertJsonPath('data.fulfillment_summary.parts_count', 2)
            ->assertJsonPath('data.fulfillment_summary.open_issues_count', 2)
            ->assertJsonPath('data.manager_access.full_order_visible', true)
            ->assertJsonPath('data.manager_access.read_only_contract', true)
            ->assertJsonPath(
                'data.manager_access.assigned_fulfillment_order_ids.0',
                $assignedPart->id
            )
            ->assertJsonCount(2, 'data.partial_orders')
            ->assertJsonCount(2, 'data.fulfillment_issues')
            ->assertJsonCount(2, 'data.inventory_movements')
            ->assertJsonCount(1, 'data.status_history');

        $issues = collect($response->json('data.fulfillment_issues'))->keyBy('id');
        $this->assertTrue($issues[$assignedIssue->id]['manager_accessible']);
        $this->assertTrue($issues[$assignedIssue->id]['actions']['can_take']);
        $this->assertFalse($issues[$foreignIssue->id]['manager_accessible']);
        $this->assertFalse($issues[$foreignIssue->id]['actions']['can_take']);
    }

    public function test_filters_and_inactive_assignment_respect_the_same_scope(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $assigned = Warehouse::factory()->create();
        $foreign = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $this->assign($manager, [$assigned]);

        $withIssue = Order::factory()->pending()->create([
            'warehouse_id' => $assigned->id,
            'contact_name' => 'Иван Покупатель',
        ]);
        $this->item($withIssue, $product, 1);
        $this->issue($withIssue, $product, $assigned);
        Order::factory()->pending()->create(['warehouse_id' => $assigned->id]);

        Sanctum::actingAs($manager);
        $this->getJson('/api/manager/orders?has_issue=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withIssue->id);
        $this->getJson('/api/manager/orders?search=Иван')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $withIssue->id);
        $this->getJson("/api/manager/orders?warehouse_id={$foreign->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'manager_access_denied');

        $assigned->update(['is_active' => false]);
        $this->getJson('/api/manager/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/manager/orders/{$withIssue->id}")
            ->assertNotFound();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @param array<int, Warehouse> $warehouses */
    private function assign(User $user, array $warehouses): void
    {
        app(StaffAccessService::class)->syncActiveLocations(
            $user,
            collect($warehouses)->pluck('id')->all()
        );
    }

    private function item(Order $order, Product $product, int $quantity): OrderProduct
    {
        return $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'stock_unit' => $product->stockUnit(),
            'sale_step' => $product->saleStep(),
            'price_unit_quantity' => $product->priceUnitQuantity(),
            'unit_price' => $product->price,
            'final_unit_price' => $product->price,
            'total_price' => $product->baseTotalForQuantity($quantity),
        ]);
    }

    private function issue(
        Order $order,
        Product $product,
        Warehouse $warehouse
    ): FulfillmentIssue {
        return FulfillmentIssue::query()->create([
            'source_order_id' => $order->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'reason' => FulfillmentIssue::REASON_PHYSICAL_STOCK_DISCREPANCY,
            'shortage_quantity' => 1,
            'reserved_online_before' => 2,
            'reserved_seller_before' => 0,
            'status' => FulfillmentIssue::STATUS_WAITING,
        ]);
    }

    private function movement(
        Order $order,
        Product $product,
        Warehouse $warehouse,
        User $actor
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'order_id' => $order->id,
            'actor_id' => $actor->id,
            'type' => InventoryMovement::TYPE_ONLINE_RESERVE,
            'physical_delta' => 0,
            'reserved_online_delta' => 1,
            'reserved_seller_delta' => 0,
            'physical_before' => 10,
            'physical_after' => 10,
            'reserved_online_before' => 0,
            'reserved_online_after' => 1,
            'reserved_seller_before' => 0,
            'reserved_seller_after' => 0,
            'reason' => 'Резерв тестового заказа',
        ]);
    }
}
