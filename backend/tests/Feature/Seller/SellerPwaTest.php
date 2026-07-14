<?php

namespace Tests\Feature\Seller;

use App\Models\Discount;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerPwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_order_versions_are_idempotent_and_edit_adjusts_seller_reserve(): void
    {
        $context = $this->sellerContext(quantity: 10);
        $payload = $this->orderPayload($context, quantity: 3, revision: 1);

        $created = $this->postJson('/api/seller/orders', $payload, $context['headers'])
            ->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.status', Order::STATUS_PENDING);
        $orderId = (int) $created->json('data.id');

        // PWA-продажа не должна попадать в клиентский API заказов, даже если
        // orders.user_id указывает на того же пользователя-продавца.
        $this->getJson("/api/orders/{$orderId}")
            ->assertNotFound();

        $this->assertSame(
            3,
            (int) $context['inventory']->fresh()->reserved_seller_quantity
        );

        $this->postJson('/api/seller/orders', $payload, $context['headers'])
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('data.id', $orderId);
        $this->assertSame(
            3,
            (int) $context['inventory']->fresh()->reserved_seller_quantity
        );

        $updatedPayload = $this->orderPayload($context, quantity: 5, revision: 2);
        $this->putJson(
            "/api/seller/orders/{$orderId}",
            $updatedPayload,
            $context['headers']
        )
            ->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('data.revision', 2)
            ->assertJsonPath('data.was_edited', true)
            ->assertJsonPath('data.items.0.quantity', 5);
        $this->assertSame(
            5,
            (int) $context['inventory']->fresh()->reserved_seller_quantity
        );

        $changedSameRevision = $this->orderPayload($context, quantity: 6, revision: 2);
        $this->putJson(
            "/api/seller/orders/{$orderId}",
            $changedSameRevision,
            $context['headers']
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'seller_operation_rejected');
        $this->assertSame(
            5,
            (int) $context['inventory']->fresh()->reserved_seller_quantity
        );
    }

    public function test_clean_completion_commits_physical_stock_once(): void
    {
        $context = $this->sellerContext(quantity: 10);
        $created = $this->postJson(
            '/api/seller/orders',
            $this->orderPayload($context, quantity: 4),
            $context['headers']
        )->assertCreated();
        $orderId = (int) $created->json('data.id');
        $command = [
            'orders' => [[
                'order_id' => $orderId,
                'revision' => 1,
            ]],
        ];

        $this->postJson('/api/seller/orders/complete', $command, $context['headers'])
            ->assertOk()
            ->assertJsonPath('results.0.result', 'completed')
            ->assertJsonPath('results.0.order.status', Order::STATUS_COMPLETED);

        $inventory = $context['inventory']->fresh();
        $this->assertSame(6, (int) $inventory->quantity);
        $this->assertSame(0, (int) $inventory->reserved_seller_quantity);

        $this->postJson('/api/seller/orders/complete', $command, $context['headers'])
            ->assertOk()
            ->assertJsonPath('results.0.result', 'completed');
        $this->assertSame(6, (int) $context['inventory']->fresh()->quantity);
        $this->assertSame(
            1,
            InventoryMovement::query()
                ->where('order_id', $orderId)
                ->where('type', InventoryMovement::TYPE_SELLER_SALE)
                ->count()
        );
    }

    public function test_conflict_requires_seller_confirmation_before_manager_issue_is_created(): void
    {
        $context = $this->sellerContext(quantity: 5, reservedOnline: 4);
        $created = $this->postJson(
            '/api/seller/orders',
            $this->orderPayload($context, quantity: 3),
            $context['headers']
        )->assertCreated();
        $orderId = (int) $created->json('data.id');

        $this->postJson('/api/seller/orders/complete', [
            'orders' => [['order_id' => $orderId, 'revision' => 1]],
        ], $context['headers'])
            ->assertOk()
            ->assertJsonPath('results.0.result', 'seller_review')
            ->assertJsonPath('results.0.conflicts.0.reason', 'online_reservation_conflict')
            ->assertJsonPath('results.0.conflicts.0.shortage_quantity', 2);

        $inventory = $context['inventory']->fresh();
        $this->assertSame(5, (int) $inventory->quantity);
        $this->assertSame(3, (int) $inventory->reserved_seller_quantity);
        $this->assertSame(0, FulfillmentIssue::count());

        $this->postJson(
            "/api/seller/orders/{$orderId}/escalate",
            ['revision' => 1],
            $context['headers']
        )
            ->assertOk()
            ->assertJsonPath('result', 'manager_review')
            ->assertJsonPath('order.status', Order::STATUS_MANAGER_REVIEW)
            ->assertJsonPath('order.fulfillment_issues.0.status', FulfillmentIssue::STATUS_WAITING);

        $inventory = $context['inventory']->fresh();
        $this->assertSame(2, (int) $inventory->quantity);
        $this->assertSame(0, (int) $inventory->reserved_seller_quantity);
        $this->assertDatabaseHas('fulfillment_issues', [
            'source_order_id' => $orderId,
            'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouse']->id,
            'reason' => FulfillmentIssue::REASON_ONLINE_RESERVATION_CONFLICT,
            'shortage_quantity' => 2,
            'reserved_online_before' => 4,
            'reserved_seller_before' => 3,
            'status' => FulfillmentIssue::STATUS_WAITING,
        ]);

        $this->postJson(
            "/api/seller/orders/{$orderId}/escalate",
            ['revision' => 1],
            $context['headers']
        )->assertOk()->assertJsonPath('result', 'manager_review');
        $this->assertSame(1, FulfillmentIssue::count());
        $this->assertSame(2, (int) $context['inventory']->fresh()->quantity);

        $issue = FulfillmentIssue::firstOrFail();
        $issue->update(['status' => FulfillmentIssue::STATUS_IN_REVIEW]);
        $issue->update(['status' => FulfillmentIssue::STATUS_CLOSED]);
        $this->assertSame(
            FulfillmentIssue::STATUS_CLOSED,
            $issue->fresh()->status
        );
    }

    public function test_sync_batch_processes_each_event_independently_and_rejects_tampered_price(): void
    {
        $context = $this->sellerContext(quantity: 10);
        $valid = $this->orderPayload($context, quantity: 2);
        $tampered = $this->orderPayload(
            $context,
            quantity: 2,
            clientOrderId: (string) Str::uuid()
        );
        $tampered['items'][0]['pricing_token'] .= 'x';

        $this->postJson('/api/seller/sync', [
            'events' => [
                ['event_id' => (string) Str::uuid(), 'action' => 'upsert'] + $valid,
                ['event_id' => (string) Str::uuid(), 'action' => 'upsert'] + $tampered,
            ],
        ], $context['headers'])
            ->assertOk()
            ->assertJsonPath('results.0.result', 'accepted')
            ->assertJsonPath('results.1.result', 'rejected');

        $this->assertSame(1, Order::query()
            ->where('sales_channel', Order::SALES_CHANNEL_SELLER)
            ->count());
        $this->assertSame(
            2,
            (int) $context['inventory']->fresh()->reserved_seller_quantity
        );
    }

    public function test_signed_offline_promotion_is_applied_and_consumed_on_completion(): void
    {
        $context = $this->sellerContext(quantity: 10, promotionAttributes: [
            'name' => 'Акция PWA 10%',
            'type' => Discount::TYPE_PROMOTION,
            'value_type' => Discount::VALUE_PERCENT,
            'value' => 10,
            'is_global' => true,
            'code' => null,
            'min_order_amount' => null,
            'usage_limit' => 10,
            'usage_per_user' => 10,
            'used_count' => 0,
        ]);
        $promotion = $context['promotion'];

        $created = $this->postJson(
            '/api/seller/orders',
            $this->orderPayload($context, quantity: 2),
            $context['headers']
        )
            ->assertCreated()
            ->assertJsonPath('data.totals.promotion_discount', 20)
            ->assertJsonPath('data.totals.final_total', 180);

        $this->postJson('/api/seller/orders/complete', [
            'orders' => [[
                'order_id' => (int) $created->json('data.id'),
                'revision' => 1,
            ]],
        ], $context['headers'])
            ->assertOk()
            ->assertJsonPath('results.0.result', 'completed');

        $this->assertSame(2, (int) $promotion->fresh()->used_count);
        $this->assertSame(
            2,
            (int) $promotion->users()
                ->where('users.id', $context['seller']->id)
                ->firstOrFail()
                ->pivot
                ->used_count
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerContext(
        int $quantity,
        int $reservedOnline = 0,
        ?array $promotionAttributes = null
    ): array
    {
        $seller = User::factory()->create();
        $seller->assignRole(User::ROLE_SELLER);
        $warehouse = Warehouse::factory()->create();
        app(StaffAccessService::class)->syncActiveLocations($seller, [$warehouse->id]);
        $product = Product::factory()->create([
            'price' => 100,
            'stock_unit' => Product::STOCK_UNIT_PIECE,
            'sale_step' => 1,
            'price_unit_quantity' => 1,
        ]);
        $promotion = $promotionAttributes !== null
            ? Discount::factory()->active()->create($promotionAttributes + [
                'created_by' => $seller->id,
            ])
            : null;
        $inventory = Inventory::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_online_quantity' => $reservedOnline,
            'reserved_seller_quantity' => 0,
        ]);

        Sanctum::actingAs($seller);
        $deviceUuid = (string) Str::uuid();
        $this->postJson('/api/seller/devices/register', [
            'device_uuid' => $deviceUuid,
            'name' => 'Касса тестового магазина',
            'warehouse_id' => $warehouse->id,
        ])->assertCreated();
        $this->assertDatabaseHas('staff_devices', [
            'user_id' => $seller->id,
            'device_uuid' => $deviceUuid,
            'personal_access_token_id' => null,
        ]);
        $headers = ['X-Device-UUID' => $deviceUuid];
        $bootstrap = $this->getJson(
            "/api/seller/bootstrap?warehouse_id={$warehouse->id}",
            $headers
        )->assertOk();
        $products = collect($bootstrap->json('data.products'));
        $productData = $products->firstWhere('id', $product->id);

        return [
            'seller' => $seller,
            'warehouse' => $warehouse,
            'product' => $product,
            'inventory' => $inventory,
            'promotion' => $promotion,
            'device_uuid' => $deviceUuid,
            'headers' => $headers,
            'pricing_token' => $productData['pricing_token'],
            'occurred_at' => $productData['pricing']['issued_at'],
            'client_order_id' => (string) Str::uuid(),
        ];
    }

    private function orderPayload(
        array $context,
        int $quantity,
        int $revision = 1,
        ?string $clientOrderId = null
    ): array {
        return [
            'client_order_id' => $clientOrderId ?? $context['client_order_id'],
            'revision' => $revision,
            'warehouse_id' => $context['warehouse']->id,
            'occurred_at' => $context['occurred_at'],
            'payment_method' => Order::PAYMENT_CARD,
            'items' => [[
                'product_id' => $context['product']->id,
                'quantity' => $quantity,
                'pricing_token' => $context['pricing_token'],
            ]],
        ];
    }
}
