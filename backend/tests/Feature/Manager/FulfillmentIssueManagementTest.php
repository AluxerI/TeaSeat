<?php

namespace Tests\Feature\Manager;

use App\Models\FulfillmentIssue;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StaffAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentIssueManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manager_sees_only_open_issues_from_assigned_locations_and_admin_sees_all(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $admin = $this->staff(User::ROLE_ADMIN);
        $customer = $this->staff(User::ROLE_USER);
        $assigned = Warehouse::factory()->create();
        $foreign = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $this->assign($manager, [$assigned]);

        $waiting = $this->issue($assigned, $product);
        $this->issue($foreign, $product);
        $closed = $this->issue(
            $assigned,
            $product,
            FulfillmentIssue::STATUS_CLOSED,
            $manager
        );

        Sanctum::actingAs($manager);
        $this->getJson('/api/manager/fulfillment-issues')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $waiting->id)
            ->assertJsonPath('summary.waiting', 1)
            ->assertJsonPath('summary.closed', 1);

        $this->getJson('/api/manager/fulfillment-issues?status=all')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->getJson("/api/manager/fulfillment-issues/{$closed->id}")
            ->assertOk();

        Sanctum::actingAs($admin);
        $this->getJson('/api/manager/fulfillment-issues?status=all')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        Sanctum::actingAs($customer);
        $this->getJson('/api/manager/fulfillment-issues')->assertForbidden();
    }

    public function test_take_release_and_close_are_owned_idempotent_and_concurrency_safe(): void
    {
        $firstManager = $this->staff(User::ROLE_MANAGER);
        $secondManager = $this->staff(User::ROLE_MANAGER);
        $admin = $this->staff(User::ROLE_ADMIN);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $this->assign($firstManager, [$warehouse]);
        $this->assign($secondManager, [$warehouse]);
        $issue = $this->issue($warehouse, $product);

        Sanctum::actingAs($firstManager);
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/take")
            ->assertOk()
            ->assertJsonPath('data.status', FulfillmentIssue::STATUS_IN_REVIEW)
            ->assertJsonPath('data.manager_id', $firstManager->id);
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/take")
            ->assertOk();
        $this->getJson('/api/manager/fulfillment-issues?status=all&mine=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $issue->id);

        Sanctum::actingAs($secondManager);
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/take")
            ->assertStatus(409);
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/release")
            ->assertStatus(409);

        Sanctum::actingAs($admin);
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/release")
            ->assertOk()
            ->assertJsonPath('data.status', FulfillmentIssue::STATUS_WAITING)
            ->assertJsonPath('data.manager_id', null);

        Sanctum::actingAs($secondManager);
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/take")
            ->assertOk();
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', FulfillmentIssue::STATUS_CLOSED)
            ->assertJsonPath('data.manager_id', $secondManager->id)
            ->assertJsonPath('data.actions.can_reopen', false);
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/close")
            ->assertOk();
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/take")
            ->assertStatus(409);
        $this->postJson("/api/manager/fulfillment-issues/{$issue->id}/release")
            ->assertStatus(409);

        $this->assertDatabaseHas('fulfillment_issues', [
            'id' => $issue->id,
            'status' => FulfillmentIssue::STATUS_CLOSED,
            'manager_id' => $secondManager->id,
        ]);
    }

    public function test_inactive_assignment_immediately_removes_manager_access(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $admin = $this->staff(User::ROLE_ADMIN);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $this->assign($manager, [$warehouse]);
        $issue = $this->issue($warehouse, $product);

        $warehouse->update(['is_active' => false]);

        Sanctum::actingAs($manager);
        $this->getJson('/api/manager/fulfillment-issues')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/manager/fulfillment-issues/{$issue->id}")
            ->assertNotFound();

        Sanctum::actingAs($admin);
        $this->getJson("/api/manager/fulfillment-issues/{$issue->id}")
            ->assertOk();
    }

    public function test_affected_orders_are_dynamic_filtered_and_sorted_without_selecting_a_victim(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $warehouse = Warehouse::factory()->create();
        $otherWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $this->assign($manager, [$warehouse]);
        $issue = $this->issue($warehouse, $product, shortageQuantity: 3);

        $larger = $this->onlineReservation($warehouse, $product, 8);
        $smaller = $this->onlineReservation($warehouse, $product, 2);
        $this->onlineReservation($otherWarehouse, $product, 20);
        $this->onlineReservation($warehouse, $otherProduct, 20);
        $this->onlineReservation($warehouse, $product, 7, released: true);
        $this->onlineReservation($warehouse, $product, 6, committed: true);
        $this->onlineReservation($warehouse, $product, 5, cancelled: true);

        Sanctum::actingAs($manager);
        $this->getJson(
            "/api/manager/fulfillment-issues/{$issue->id}/affected-orders"
        )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.order_product_id', $larger->id)
            ->assertJsonPath('data.0.reserved_quantity', 8)
            ->assertJsonPath('data.1.order_product_id', $smaller->id)
            ->assertJsonPath('data.1.reserved_quantity', 2)
            ->assertJsonPath('summary.candidate_orders_count', 2)
            ->assertJsonPath('summary.reserved_quantity_total', 10)
            ->assertJsonPath('summary.shortage_quantity', 3);

        $this->assertSame(FulfillmentIssue::STATUS_WAITING, $issue->fresh()->status);
        $this->assertNull($issue->fresh()->manager_id);
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

    private function issue(
        Warehouse $warehouse,
        Product $product,
        string $status = FulfillmentIssue::STATUS_WAITING,
        ?User $manager = null,
        int $shortageQuantity = 2
    ): FulfillmentIssue {
        $seller = $this->staff(User::ROLE_SELLER);
        $sourceOrder = Order::factory()->seller()->create([
            'user_id' => $seller->id,
            'warehouse_id' => $warehouse->id,
            'status' => Order::STATUS_MANAGER_REVIEW,
            'client_order_id' => (string) Str::uuid(),
            'seller_revision' => 1,
            'stock_reserved_at' => now(),
            'stock_committed_at' => now(),
            'seller_escalated_at' => now(),
        ]);
        $this->createOrderItem($sourceOrder, $product, $shortageQuantity);

        return FulfillmentIssue::query()->create([
            'source_order_id' => $sourceOrder->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'manager_id' => $manager?->id,
            'reason' => FulfillmentIssue::REASON_ONLINE_RESERVATION_CONFLICT,
            'shortage_quantity' => $shortageQuantity,
            'reserved_online_before' => 10,
            'reserved_seller_before' => $shortageQuantity,
            'status' => $status,
        ]);
    }

    private function onlineReservation(
        Warehouse $warehouse,
        Product $product,
        int $quantity,
        bool $released = false,
        bool $committed = false,
        bool $cancelled = false
    ): OrderProduct {
        $customer = User::factory()->create();
        $mainOrder = Order::factory()->pending()->create([
            'user_id' => $customer->id,
            'warehouse_id' => null,
            'stock_reserved_at' => now(),
        ]);
        $fulfillmentOrder = Order::factory()->pending()->create([
            'user_id' => $customer->id,
            'parent_order_id' => $mainOrder->id,
            'warehouse_id' => $warehouse->id,
            'status' => $cancelled ? Order::STATUS_CANCELLED : Order::STATUS_PENDING,
            'stock_reserved_at' => now(),
            'stock_released_at' => $released ? now() : null,
            'stock_committed_at' => $committed ? now() : null,
        ]);

        return $this->createOrderItem($fulfillmentOrder, $product, $quantity);
    }

    private function createOrderItem(
        Order $order,
        Product $product,
        int $quantity
    ): OrderProduct {
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
}
