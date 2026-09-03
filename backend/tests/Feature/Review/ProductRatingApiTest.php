<?php

namespace Tests\Feature\Review;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductRatingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_product_resources_use_only_published_verified_reviews(): void
    {
        $product = Product::factory()->create([
            'is_available' => true,
            'total_quantity' => 10,
        ]);
        $warehouse = Warehouse::factory()->create([
            'is_active' => true,
            'is_online_fulfillment_enabled' => true,
        ]);
        Inventory::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]);

        $this->review($product, 5, Review::STATUS_PUBLISHED, verified: true);
        $this->review($product, 3, Review::STATUS_PUBLISHED, verified: true);
        $this->review($product, 1, Review::STATUS_HIDDEN, verified: true);
        $this->review($product, 1, Review::STATUS_PUBLISHED, verified: false);

        $catalog = $this->getJson('/api/catalog')->assertOk();
        $catalogProduct = collect($catalog->json('data.products'))
            ->firstWhere('id', $product->id);

        $this->assertNotNull($catalogProduct);
        $this->assertSame(4.0, (float) $catalogProduct['rating_average']);
        $this->assertSame(2, (int) $catalogProduct['reviews_count']);

        $this->getJson("/api/item/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.rating_average', 4)
            ->assertJsonPath('data.reviews_count', 2);

        $customer = User::factory()->create();
        Wishlist::query()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
        Sanctum::actingAs($customer);
        $this->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonPath('data.0.product.rating_average', 4)
            ->assertJsonPath('data.0.product.reviews_count', 2);
    }

    public function test_product_without_reviews_has_empty_rating(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/item/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.rating_average', null)
            ->assertJsonPath('data.reviews_count', 0);
    }

    private function review(
        Product $product,
        int $rating,
        string $status,
        bool $verified
    ): Review {
        $customer = User::factory()->create();
        $orderProduct = $verified
            ? $this->purchasedItem($customer, $product)
            : null;

        return Review::query()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'order_product_id' => $orderProduct?->id,
            'rating' => $rating,
            'comment' => 'Тестовый отзыв',
            'status' => $status,
        ]);
    }

    private function purchasedItem(User $customer, Product $product): OrderProduct
    {
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'sales_channel' => Order::SALES_CHANNEL_ONLINE,
            'status' => Order::STATUS_DELIVERED,
        ]);

        return $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'stock_unit' => Product::STOCK_UNIT_PIECE,
            'sale_step' => 1,
            'price_unit_quantity' => 1,
            'unit_price' => 100,
            'final_unit_price' => 100,
            'total_price' => 100,
        ]);
    }
}
