<?php

namespace Tests\Feature\Delivery;

use App\Models\AddressClient;
use App\Models\Brand;
use App\Models\DeliveryMethod;
use App\Models\DeliveryTimeSlot;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StaffAccessService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliverySchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-10 08:00:00');
        CarbonImmutable::setTestNow('2026-08-10 08:00:00');
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_customer_catalog_shows_only_available_capacity_for_own_address(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $address = $this->address($customer);
        $method = $this->method();
        $slot = $this->slot($method, 1, '10:00', '14:00', 2);

        $this->scheduledOrder($customer, $method, $slot, '2026-08-10');

        Sanctum::actingAs($customer);
        $this->getJson(
            "/api/checkout/delivery-slots/{$address->id}/{$method->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.booking_horizon_days', 30)
            ->assertJsonPath('data.dates.0.date', '2026-08-10')
            ->assertJsonPath('data.dates.0.slots.0.id', $slot->id)
            ->assertJsonPath('data.dates.0.slots.0.booked', 1)
            ->assertJsonPath('data.dates.0.slots.0.remaining_capacity', 1);

        $this->scheduledOrder($otherCustomer, $method, $slot, '2026-08-10');
        $response = $this->getJson(
            "/api/checkout/delivery-slots/{$address->id}/{$method->id}"
        )->assertOk();
        $this->assertNotContains(
            '2026-08-10',
            collect($response->json('data.dates'))->pluck('date')->all()
        );

        Sanctum::actingAs($otherCustomer);
        $this->getJson(
            "/api/checkout/delivery-slots/{$address->id}/{$method->id}"
        )->assertUnprocessable();
    }

    public function test_checkout_requires_slot_and_saves_server_schedule_snapshot(): void
    {
        $customer = User::factory()->create();
        $address = $this->address($customer);
        $method = $this->method();
        $slot = $this->slot($method, 2, '10:00', '14:00', 1);
        $warehouse = Warehouse::factory()->deliveryHub()->create([
            'city' => 'Тула',
        ]);
        $brand = Brand::query()->create(['name' => 'Delivery test brand']);
        $product = new Product([
            'name' => 'Delivery test tea',
            'brand_id' => $brand->id,
            'price' => 100,
            'stock_unit' => Product::STOCK_UNIT_PIECE,
            'sale_step' => 1,
            'price_unit_quantity' => 1,
            'weight_grams' => 100,
            'is_available' => true,
            'total_quantity' => 5,
        ]);
        $product->save();
        Inventory::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'reserved_online_quantity' => 0,
            'reserved_seller_quantity' => 0,
        ]);
        $cart = Order::factory()->cart()->create([
            'user_id' => $customer->id,
            'products_total' => 100,
            'final_total' => 100,
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'stock_unit' => Product::STOCK_UNIT_PIECE,
            'sale_step' => 1,
            'price_unit_quantity' => 1,
            'unit_price' => 100,
            'final_unit_price' => 100,
            'total_price' => 100,
        ]);

        Sanctum::actingAs($customer);
        $payload = [
            'shipping_address_id' => $address->id,
            'delivery_method_id' => $method->id,
            'payment_method' => Order::PAYMENT_CARD,
        ];
        $this->withHeader('Idempotency-Key', 'delivery-slot-missing')
            ->postJson('/api/checkout', $payload)
            ->assertUnprocessable();

        $this->withHeader('Idempotency-Key', 'delivery-slot-selected')
            ->postJson('/api/checkout', $payload + [
                'scheduled_delivery_date' => '2026-08-11',
                'delivery_time_slot_id' => $slot->id,
            ])
            ->assertSuccessful()
            ->assertJsonPath(
                'data.delivery.scheduled_window.date',
                '2026-08-11'
            )
            ->assertJsonPath(
                'data.delivery.scheduled_window.time_from',
                '10:00'
            );

        $order = $cart->fresh();
        $this->assertSame($slot->id, $order->delivery_time_slot_id);
        $this->assertSame('2026-08-11', $order->scheduled_delivery_date->toDateString());
        $this->assertSame('10:00', substr((string) $order->delivery_time_from, 0, 5));
        $this->assertSame('14:00', substr((string) $order->delivery_time_to, 0, 5));
    }

    public function test_manager_reschedules_scoped_order_and_keeps_audit_note(): void
    {
        $warehouse = Warehouse::factory()->create(['city' => 'Тула']);
        $manager = $this->staff(User::ROLE_MANAGER, $warehouse);
        $courier = $this->staff(User::ROLE_COURIER, $warehouse);
        $customer = User::factory()->create();
        $method = $this->method();
        $oldSlot = $this->slot($method, 2, '10:00', '14:00', 2);
        $newSlot = $this->slot($method, 3, '14:00', '18:00', 2);
        $order = $this->scheduledOrder(
            $customer,
            $method,
            $oldSlot,
            '2026-08-11',
            Order::STATUS_PENDING,
            $warehouse
        );

        Sanctum::actingAs($manager);
        $payload = [
            'scheduled_delivery_date' => '2026-08-12',
            'delivery_time_slot_id' => $newSlot->id,
            'reason' => 'Клиент попросил перенести доставку',
        ];
        $this->postJson("/api/manager/orders/{$order->id}/reschedule", $payload)
            ->assertOk()
            ->assertJsonPath(
                'data.delivery.scheduled_window.date',
                '2026-08-12'
            )
            ->assertJsonPath('data.actions.can_reschedule', true);

        $notes = (string) $order->fresh()->internal_notes;
        $this->assertStringContainsString(
            'Клиент попросил перенести доставку',
            $notes
        );
        $this->assertStringContainsString('2026-08-11 10:00–14:00', $notes);
        $this->assertStringContainsString('2026-08-12 14:00–18:00', $notes);

        $this->postJson("/api/manager/orders/{$order->id}/reschedule", $payload)
            ->assertOk();
        $this->assertSame($notes, (string) $order->fresh()->internal_notes);

        $order->update([
            'courier_id' => $courier->id,
            'courier_assigned_at' => now(),
        ]);
        $this->postJson("/api/manager/orders/{$order->id}/reschedule", [
            'scheduled_delivery_date' => '2026-08-11',
            'delivery_time_slot_id' => $oldSlot->id,
            'reason' => 'Попытка после назначения курьера',
        ])->assertConflict();
    }

    public function test_courier_sees_order_only_in_claim_window_and_release_requires_reason(): void
    {
        $warehouse = Warehouse::factory()->create(['city' => 'Тула']);
        $courier = $this->staff(User::ROLE_COURIER, $warehouse);
        $customer = User::factory()->create();
        $method = $this->method();
        $slot = $this->slot($method, 3, '10:00', '14:00', 3);
        $order = $this->scheduledOrder(
            $customer,
            $method,
            $slot,
            '2026-08-12',
            Order::STATUS_READY_FOR_DELIVERY,
            $warehouse
        );

        Sanctum::actingAs($courier);
        $this->getJson('/api/courier/deliveries')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->postJson("/api/courier/deliveries/{$order->id}/claim")
            ->assertConflict()
            ->assertJsonPath('code', 'delivery_transition_rejected');

        Carbon::setTestNow('2026-08-11 10:01:00');
        CarbonImmutable::setTestNow('2026-08-11 10:01:00');
        $this->getJson('/api/courier/deliveries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actions.can_claim', true)
            ->assertJsonPath(
                'data.0.scheduled_window.claim_opens_at',
                fn ($value): bool => is_string($value) && $value !== ''
            );
        $this->postJson("/api/courier/deliveries/{$order->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.courier.id', $courier->id);

        $this->postJson("/api/courier/deliveries/{$order->id}/release")
            ->assertUnprocessable();
        $this->postJson("/api/courier/deliveries/{$order->id}/release", [
            'reason' => 'Не смогу выйти на смену',
        ])
            ->assertOk()
            ->assertJsonPath('data.courier', null)
            ->assertJsonPath('data.actions.can_claim', true);

        $order->refresh();
        $this->assertNull($order->courier_id);
        $this->assertNull($order->courier_assigned_at);
        $this->assertStringContainsString(
            'Не смогу выйти на смену',
            (string) $order->internal_notes
        );
    }

    private function method(): DeliveryMethod
    {
        return DeliveryMethod::query()->create([
            'name' => 'Курьерская доставка ' . fake()->uuid(),
            'cost' => 0,
            'estimated_days_min' => 0,
            'estimated_days_max' => 3,
            'is_active' => true,
            'available_cities' => ['Тула'],
            'type' => DeliveryMethod::TYPE_COURIER,
        ]);
    }

    private function slot(
        DeliveryMethod $method,
        int $weekday,
        string $from,
        string $to,
        int $capacity
    ): DeliveryTimeSlot {
        return DeliveryTimeSlot::query()->create([
            'delivery_method_id' => $method->id,
            'weekday' => $weekday,
            'time_from' => $from,
            'time_to' => $to,
            'capacity' => $capacity,
            'is_active' => true,
        ]);
    }

    private function address(User $user): AddressClient
    {
        return AddressClient::query()->create([
            'user_id' => $user->id,
            'street' => 'Советская, 1',
            'city' => 'Тула',
            'postal_code' => '300000',
        ]);
    }

    private function scheduledOrder(
        User $customer,
        DeliveryMethod $method,
        DeliveryTimeSlot $slot,
        string $date,
        string $status = Order::STATUS_PENDING,
        ?Warehouse $warehouse = null
    ): Order {
        return Order::factory()->create([
            'user_id' => $customer->id,
            'sales_channel' => Order::SALES_CHANNEL_ONLINE,
            'status' => $status,
            'warehouse_id' => $warehouse?->id,
            'delivery_method_id' => $method->id,
            'delivery_time_slot_id' => $slot->id,
            'scheduled_delivery_date' => $date,
            'delivery_time_from' => $slot->timeFrom(),
            'delivery_time_to' => $slot->timeTo(),
            'confirmed_at' => now(),
        ]);
    }

    private function staff(string $role, Warehouse $warehouse): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        app(StaffAccessService::class)->syncActiveLocations(
            $user,
            [$warehouse->id]
        );

        return $user;
    }
}
