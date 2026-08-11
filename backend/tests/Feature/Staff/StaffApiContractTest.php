<?php

namespace Tests\Feature\Staff;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_named_staff_routes_match_the_published_contract(): void
    {
        $openApi = file_get_contents(base_path('docs/openapi/staff-api.yaml'));
        $this->assertIsString($openApi);

        foreach ($this->contractRoutes() as $name => [$method, $uri, $permission]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertInstanceOf(
                LaravelRoute::class,
                $route,
                "Маршрут {$name} отсутствует"
            );
            $this->assertSame($uri, $route->uri(), "Изменился URI маршрута {$name}");
            $this->assertContains($method, $route->methods(), "Изменился метод маршрута {$name}");

            $middleware = $route->gatherMiddleware();
            $this->assertContains(
                'auth:sanctum',
                $middleware,
                "Маршрут {$name} больше не защищён Sanctum"
            );
            if ($permission !== null) {
                $this->assertContains(
                    "permission:{$permission}",
                    $middleware,
                    "Изменилось право маршрута {$name}"
                );
            }
            if (str_starts_with($name, 'management.')) {
                $this->assertContains(
                    'role:admin',
                    $middleware,
                    "Глобальный маршрут {$name} больше не ограничен ролью admin"
                );
            }

            $this->assertSame(
                1,
                substr_count($openApi, "x-laravel-route-name: {$name}"),
                "Маршрут {$name} не зафиксирован ровно один раз в OpenAPI"
            );
        }

        $syncMiddleware = Route::getRoutes()
            ->getByName('seller.sync')
            ->gatherMiddleware();
        $this->assertContains('permission:complete own seller orders', $syncMiddleware);
    }

    public function test_management_workflow_rejection_has_a_stable_conflict_response(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(User::ROLE_ADMIN);
        $order = Order::factory()->pending()->create([
            'sales_channel' => Order::SALES_CHANNEL_ONLINE,
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/orders/{$order->id}/ship")
            ->assertConflict()
            ->assertJsonPath('code', 'order_transition_rejected')
            ->assertJsonStructure(['message', 'code']);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    private function contractRoutes(): array
    {
        return [
            'user.show' => ['GET', 'api/user', null],

            'seller.devices.register' => ['POST', 'api/seller/devices/register', 'create seller orders'],
            'seller.bootstrap' => ['GET', 'api/seller/bootstrap', 'create seller orders'],
            'seller.orders.index' => ['GET', 'api/seller/orders', 'view own seller orders'],
            'seller.orders.show' => ['GET', 'api/seller/orders/{order}', 'view own seller orders'],
            'seller.orders.store' => ['POST', 'api/seller/orders', 'create seller orders'],
            'seller.orders.update' => ['PUT', 'api/seller/orders/{order}', 'create seller orders'],
            'seller.orders.cancel' => ['POST', 'api/seller/orders/{order}/cancel', 'create seller orders'],
            'seller.orders.complete' => ['POST', 'api/seller/orders/complete', 'complete own seller orders'],
            'seller.orders.escalate' => ['POST', 'api/seller/orders/{order}/escalate', 'complete own seller orders'],
            'seller.sync' => ['POST', 'api/seller/sync', 'create seller orders'],

            'picker.transfers.index' => ['GET', 'api/picker/incoming-transfers', 'view picking orders'],
            'picker.transfers.receive' => ['POST', 'api/picker/incoming-transfers/{order}/receive', 'manage own picking orders'],
            'picker.orders.index' => ['GET', 'api/picker/orders', 'view picking orders'],
            'picker.orders.show' => ['GET', 'api/picker/orders/{order}', 'view picking orders'],
            'picker.orders.take' => ['POST', 'api/picker/orders/{order}/take', 'manage own picking orders'],
            'picker.orders.release' => ['POST', 'api/picker/orders/{order}/release', 'manage own picking orders'],
            'picker.orders.complete' => ['POST', 'api/picker/orders/{order}/complete', 'manage own picking orders'],
            'picker.orders.escalate' => ['POST', 'api/picker/orders/{order}/escalate', 'manage own picking orders'],
            'picker.orders.shortage' => ['POST', 'api/picker/orders/{order}/shortage', 'report picking shortage'],

            'courier.deliveries.index' => ['GET', 'api/courier/deliveries', 'view assigned deliveries'],
            'courier.deliveries.show' => ['GET', 'api/courier/deliveries/{order}', 'view assigned deliveries'],
            'courier.deliveries.claim' => ['POST', 'api/courier/deliveries/{order}/claim', 'update assigned deliveries'],
            'courier.deliveries.release' => ['POST', 'api/courier/deliveries/{order}/release', 'update assigned deliveries'],
            'courier.deliveries.start' => ['POST', 'api/courier/deliveries/{order}/start', 'update assigned deliveries'],
            'courier.deliveries.deliver' => ['POST', 'api/courier/deliveries/{order}/deliver', 'update assigned deliveries'],

            'manager.orders.return-to-stock' => ['POST', 'api/manager/orders/{order}/return-to-stock', 'manage orders'],
            'manager.orders.index' => ['GET', 'api/manager/orders', 'view manager orders'],
            'manager.orders.show' => ['GET', 'api/manager/orders/{order}', 'view manager orders'],
            'manager.orders.internal-notes' => ['POST', 'api/manager/orders/{order}/internal-notes', 'manage manager orders'],
            'manager.orders.confirm' => ['POST', 'api/manager/orders/{order}/confirm', 'manage manager orders'],
            'manager.orders.cancel' => ['POST', 'api/manager/orders/{order}/cancel', 'manage manager orders'],
            'manager.deliveries.assign-courier' => ['POST', 'api/manager/deliveries/{order}/assign-courier', 'assign couriers'],
            'manager.fulfillment-issues.index' => ['GET', 'api/manager/fulfillment-issues', 'view fulfillment issues'],
            'manager.fulfillment-issues.show' => ['GET', 'api/manager/fulfillment-issues/{issue}', 'view fulfillment issues'],
            'manager.fulfillment-issues.affected-orders' => ['GET', 'api/manager/fulfillment-issues/{issue}/affected-orders', 'view fulfillment issues'],
            'manager.fulfillment-issues.take' => ['POST', 'api/manager/fulfillment-issues/{issue}/take', 'manage fulfillment issues'],
            'manager.fulfillment-issues.release' => ['POST', 'api/manager/fulfillment-issues/{issue}/release', 'manage fulfillment issues'],
            'manager.fulfillment-issues.close' => ['POST', 'api/manager/fulfillment-issues/{issue}/close', 'manage fulfillment issues'],

            'management.orders.index' => ['GET', 'api/admin/orders', 'manage orders'],
            'management.orders.stats' => ['GET', 'api/admin/orders/stats', 'manage orders'],
            'management.orders.show' => ['GET', 'api/admin/orders/{order}', 'manage orders'],
            'management.orders.update-status' => ['PUT', 'api/admin/orders/{order}/status', 'manage orders'],
            'management.orders.update-tracking' => ['PUT', 'api/admin/orders/{order}/tracking', 'manage orders'],
            'management.orders.update-internal-notes' => ['PUT', 'api/admin/orders/{order}/internal-notes', 'manage orders'],
            'management.orders.cancel' => ['PUT', 'api/admin/orders/{order}/cancel', 'manage orders'],
            'management.orders.confirm' => ['PUT', 'api/admin/orders/{order}/confirm', 'manage orders'],
            'management.orders.ship' => ['PUT', 'api/admin/orders/{order}/ship', 'manage orders'],
            'management.orders.deliver' => ['PUT', 'api/admin/orders/{order}/deliver', 'manage orders'],
            'management.orders.update-delivery-method' => ['PUT', 'api/admin/orders/{order}/delivery-method', 'manage orders'],
        ];
    }
}
