<?php

namespace Tests\Feature\Manager;

use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StaffAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_customer_creates_lists_and_withdraws_request_without_changing_order(): void
    {
        [$customer, $order] = $this->customerOrder();
        Sanctum::actingAs($customer);

        $this->postJson("/api/orders/{$order->id}/requests", [
            'type' => OrderRequest::TYPE_OTHER,
        ])->assertUnprocessable();

        $created = $this->postJson("/api/orders/{$order->id}/requests", [
            'type' => OrderRequest::TYPE_OTHER,
            'message' => 'Пожалуйста, позвоните мне по поводу подъезда.',
        ])->assertCreated()
            ->assertJsonPath('data.status', OrderRequest::STATUS_WAITING)
            ->assertJsonPath('data.can_withdraw', true)
            ->json('data');

        $this->postJson("/api/orders/{$order->id}/requests", [
            'type' => OrderRequest::TYPE_OTHER,
            'message' => 'Повторное обращение.',
        ])->assertConflict();

        $this->getJson("/api/orders/{$order->id}/requests")
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->postJson("/api/order-requests/{$created['id']}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.status', OrderRequest::STATUS_WITHDRAWN)
            ->assertJsonPath('data.can_withdraw', false);

        $this->postJson("/api/orders/{$order->id}/requests", [
            'type' => OrderRequest::TYPE_OTHER,
            'message' => 'Новое обращение после отзыва предыдущего.',
        ])->assertCreated();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(2, $order->requests()->count());
    }

    public function test_request_access_and_order_status_rules_are_enforced(): void
    {
        [$customer, $order] = $this->customerOrder();
        $otherCustomer = User::factory()->create();
        Sanctum::actingAs($otherCustomer);
        $this->getJson("/api/orders/{$order->id}/requests")->assertNotFound();

        $order->update(['status' => Order::STATUS_DELIVERED]);
        Sanctum::actingAs($customer);
        $this->postJson("/api/orders/{$order->id}/requests", [
            'type' => OrderRequest::TYPE_CHANGE_DELIVERY,
        ])->assertConflict();
        $this->postJson("/api/orders/{$order->id}/requests", [
            'type' => OrderRequest::TYPE_ORDER_PROBLEM,
            'message' => 'Заказ доставлен с повреждённой упаковкой.',
        ])->assertCreated();

        $order->update(['status' => Order::STATUS_COMPLETED]);
        $this->postJson("/api/orders/{$order->id}/requests", [
            'type' => OrderRequest::TYPE_OTHER,
            'message' => 'Закрытый заказ.',
        ])->assertConflict();
    }

    public function test_manager_queue_is_scoped_and_transitions_are_owned(): void
    {
        [$customer, $order, $warehouse] = $this->customerOrder();
        $requestItem = $order->requests()->create([
            'user_id' => $customer->id,
            'type' => OrderRequest::TYPE_CANCEL_ORDER,
            'message' => 'Прошу отменить заказ.',
            'status' => OrderRequest::STATUS_WAITING,
        ]);
        $firstManager = $this->manager();
        $secondManager = $this->manager();

        Sanctum::actingAs($firstManager);
        $this->getJson('/api/manager/order-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        app(StaffAccessService::class)->syncActiveLocations($firstManager, [$warehouse->id]);
        app(StaffAccessService::class)->syncActiveLocations($secondManager, [$warehouse->id]);
        $this->getJson('/api/manager/order-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.waiting', 1);
        $this->postJson("/api/manager/order-requests/{$requestItem->id}/take")
            ->assertOk()
            ->assertJsonPath('data.status', OrderRequest::STATUS_IN_REVIEW)
            ->assertJsonPath('data.manager.id', $firstManager->id);

        Sanctum::actingAs($secondManager);
        $this->postJson("/api/manager/order-requests/{$requestItem->id}/take")
            ->assertConflict();
        $this->postJson("/api/manager/order-requests/{$requestItem->id}/release")
            ->assertConflict();

        Sanctum::actingAs($firstManager);
        $this->postJson("/api/manager/order-requests/{$requestItem->id}/release")
            ->assertOk()
            ->assertJsonPath('data.status', OrderRequest::STATUS_WAITING);

        Sanctum::actingAs($secondManager);
        $this->postJson("/api/manager/order-requests/{$requestItem->id}/take")
            ->assertOk();
        $this->postJson("/api/manager/order-requests/{$requestItem->id}/resolve")
            ->assertUnprocessable();
        $this->postJson("/api/manager/order-requests/{$requestItem->id}/resolve", [
            'manager_comment' => 'Отмена согласована, заказ передан в обработку менеджеру.',
        ])->assertOk()
            ->assertJsonPath('data.status', OrderRequest::STATUS_RESOLVED)
            ->assertJsonPath('data.manager.id', $secondManager->id);

        Sanctum::actingAs($customer);
        $this->getJson("/api/orders/{$order->id}/requests")
            ->assertOk()
            ->assertJsonPath('data.0.manager_comment', 'Отмена согласована, заказ передан в обработку менеджеру.');
    }

    private function customerOrder(): array
    {
        $customer = User::factory()->create();
        $warehouse = Warehouse::query()->create([
            'name' => 'Склад обращений',
            'city' => 'Москва',
            'type' => Warehouse::TYPE_WAREHOUSE,
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
        ]);
        $order = Order::factory()->pending()->create([
            'user_id' => $customer->id,
            'sales_channel' => Order::SALES_CHANNEL_ONLINE,
            'warehouse_id' => $warehouse->id,
        ]);

        return [$customer, $order, $warehouse];
    }

    private function manager(): User
    {
        $manager = User::factory()->create();
        $manager->assignRole(User::ROLE_MANAGER);

        return $manager;
    }
}
