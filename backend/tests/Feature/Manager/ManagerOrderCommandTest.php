<?php

namespace Tests\Feature\Manager;

use App\Models\FulfillmentIssue;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StaffAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManagerOrderCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_internal_notes_are_appended_without_requiring_all_order_locations(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $assigned = Warehouse::factory()->create();
        $foreign = Warehouse::factory()->create();
        $this->assign($manager, [$assigned]);

        $order = Order::factory()->pending()->create([
            'warehouse_id' => null,
            'internal_notes' => 'Старая заметка',
        ]);
        Order::factory()->pending()->create([
            'parent_order_id' => $order->id,
            'warehouse_id' => $assigned->id,
        ]);
        Order::factory()->pending()->create([
            'parent_order_id' => $order->id,
            'warehouse_id' => $foreign->id,
        ]);
        $inaccessible = Order::factory()->pending()->create([
            'warehouse_id' => $foreign->id,
        ]);

        Sanctum::actingAs($manager);
        $this->postJson("/api/manager/orders/{$order->id}/internal-notes", [
            'comment' => 'Позвонил клиенту',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.actions.can_add_internal_note', true)
            ->assertJsonPath(
                'data.manager_access.all_locations_assigned',
                false
            );
        $this->postJson("/api/manager/orders/{$order->id}/internal-notes", [
            'comment' => 'Согласовали новую дату',
        ])->assertOk();

        $notes = (string) $order->fresh()->internal_notes;
        $this->assertStringStartsWith('Старая заметка', $notes);
        $this->assertStringContainsString('Позвонил клиенту', $notes);
        $this->assertStringContainsString('Согласовали новую дату', $notes);
        $this->assertSame(2, substr_count($notes, "Менеджер #{$manager->id}"));

        $this->postJson(
            "/api/manager/orders/{$inaccessible->id}/internal-notes",
            ['comment' => 'Не должен сохраниться']
        )->assertNotFound();
        $this->postJson("/api/manager/orders/{$order->id}/internal-notes", [
            'comment' => '   ',
        ])->assertUnprocessable();
    }

    public function test_confirm_is_scoped_idempotent_and_blocked_by_open_issues(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $limitedManager = $this->staff(User::ROLE_MANAGER);
        $first = Warehouse::factory()->create();
        $second = Warehouse::factory()->create();
        $hub = Warehouse::factory()->create();
        $this->assign($manager, [$first, $second, $hub]);
        $this->assign($limitedManager, [$first]);

        $order = Order::factory()->pending()->create([
            'warehouse_id' => $hub->id,
        ]);
        $firstPart = Order::factory()->pending()->create([
            'parent_order_id' => $order->id,
            'warehouse_id' => $first->id,
            'destination_warehouse_id' => $hub->id,
        ]);
        $secondPart = Order::factory()->pending()->create([
            'parent_order_id' => $order->id,
            'warehouse_id' => $second->id,
            'destination_warehouse_id' => $hub->id,
        ]);

        Sanctum::actingAs($limitedManager);
        $this->postJson("/api/manager/orders/{$order->id}/confirm")
            ->assertForbidden()
            ->assertJsonPath('code', 'manager_access_denied');

        Sanctum::actingAs($manager);
        $this->postJson("/api/manager/orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CONFIRMED)
            ->assertJsonPath('data.actions.can_confirm', false)
            ->assertJsonPath(
                'data.manager_access.all_locations_assigned',
                true
            );
        $this->assertSame(Order::STATUS_CONFIRMED, $firstPart->fresh()->status);
        $this->assertSame(Order::STATUS_CONFIRMED, $secondPart->fresh()->status);
        $historyCount = $order->statusHistory()->count()
            + $firstPart->statusHistory()->count()
            + $secondPart->statusHistory()->count();

        $this->postJson("/api/manager/orders/{$order->id}/confirm")
            ->assertOk();
        $this->assertSame(
            $historyCount,
            $order->statusHistory()->count()
                + $firstPart->statusHistory()->count()
                + $secondPart->statusHistory()->count()
        );

        $problemOrder = Order::factory()->pending()->create([
            'warehouse_id' => $first->id,
        ]);
        $product = Product::factory()->create();
        $this->issue($problemOrder, $product, $first);

        $this->postJson("/api/manager/orders/{$problemOrder->id}/confirm")
            ->assertConflict()
            ->assertJsonPath('code', 'manager_order_transition_rejected');
        $this->assertSame(Order::STATUS_PENDING, $problemOrder->fresh()->status);

        $reviewOrder = Order::factory()->pending()->create([
            'warehouse_id' => $first->id,
            'status' => Order::STATUS_MANAGER_REVIEW,
        ]);
        $this->postJson("/api/manager/orders/{$reviewOrder->id}/confirm")
            ->assertConflict();
    }

    public function test_manager_review_cancellation_releases_reserve_once_and_paid_order_is_rejected(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $warehouse = Warehouse::factory()->create();
        $this->assign($manager, [$warehouse]);
        $product = Product::factory()->create();
        $inventory = Inventory::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'reserved_online_quantity' => 2,
            'reserved_seller_quantity' => 0,
        ]);
        $order = Order::factory()->pending()->create([
            'warehouse_id' => $warehouse->id,
            'status' => Order::STATUS_MANAGER_REVIEW,
        ]);
        $part = Order::factory()->pending()->create([
            'parent_order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'status' => Order::STATUS_MANAGER_REVIEW,
            'stock_reserved_at' => now(),
        ]);
        $this->item($part, $product, 2);
        $this->assertFalse($order->canBeCancelled());
        $this->assertTrue($order->canBeCancelledByManager());

        Sanctum::actingAs($manager);
        $this->postJson("/api/manager/orders/{$order->id}/cancel", [
            'reason' => 'Клиент согласовал отмену',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED)
            ->assertJsonPath('data.actions.can_cancel', false);

        $this->assertSame(Order::STATUS_CANCELLED, $part->fresh()->status);
        $this->assertSame(5, (int) $inventory->fresh()->quantity);
        $this->assertSame(
            0,
            (int) $inventory->fresh()->reserved_online_quantity
        );
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('order_id', $part->id)
                ->where('type', InventoryMovement::TYPE_ONLINE_RELEASE)
                ->count()
        );
        $this->assertStringContainsString(
            'Клиент согласовал отмену',
            (string) $order->fresh()->internal_notes
        );

        $this->postJson("/api/manager/orders/{$order->id}/cancel", [
            'reason' => 'Повторная доставка события',
        ])->assertOk();
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('order_id', $part->id)
                ->where('type', InventoryMovement::TYPE_ONLINE_RELEASE)
                ->count()
        );

        $paid = Order::factory()->pending()->create([
            'warehouse_id' => $warehouse->id,
            'paid_at' => now(),
        ]);
        $this->postJson("/api/manager/orders/{$paid->id}/cancel", [
            'reason' => 'Нельзя без возврата денег',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'manager_order_transition_rejected');
        $this->assertSame(Order::STATUS_PENDING, $paid->fresh()->status);
    }

    public function test_seller_orders_are_not_changed_by_manager_commands(): void
    {
        $manager = $this->staff(User::ROLE_MANAGER);
        $warehouse = Warehouse::factory()->create();
        $this->assign($manager, [$warehouse]);
        $sellerOrder = Order::factory()->seller()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        Sanctum::actingAs($manager);
        $this->postJson("/api/manager/orders/{$sellerOrder->id}/confirm")
            ->assertConflict();
        $this->postJson("/api/manager/orders/{$sellerOrder->id}/cancel", [
            'reason' => 'Проверка защиты PWA-продажи',
        ])->assertConflict();
        $this->assertSame(Order::STATUS_PENDING, $sellerOrder->fresh()->status);
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

    private function item(Order $order, Product $product, int $quantity): void
    {
        $order->items()->create([
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
            'reserved_online_before' => 1,
            'reserved_seller_before' => 0,
            'status' => FulfillmentIssue::STATUS_WAITING,
        ]);
    }
}
