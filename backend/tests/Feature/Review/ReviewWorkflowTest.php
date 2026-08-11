<?php

namespace Tests\Feature\Review;

use App\Models\ContentModerationLog;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_only_finished_purchase_creates_verified_product_review(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();
        $item = $this->purchasedItem($customer, $product, Order::STATUS_DELIVERED);

        Sanctum::actingAs($customer);
        $response = $this->postJson("/api/products/{$product->id}/reviews", [
            'order_product_id' => $item->id,
            'rating' => 5,
            'comment' => 'Отличный чай',
        ])->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.verified_purchase', true)
            ->assertJsonPath('data.status', Review::STATUS_PUBLISHED);

        $reviewId = (int) $response->json('data.id');
        $this->getJson("/api/products/{$product->id}/reviews")
            ->assertOk()
            ->assertJsonPath('summary.average', 5)
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('summary.distribution.5', 1)
            ->assertJsonPath('data.0.id', $reviewId);

        $this->postJson("/api/products/{$product->id}/reviews", [
            'order_product_id' => $item->id,
            'rating' => 4,
        ])->assertConflict()->assertJsonPath('code', 'review_rejected');

        Sanctum::actingAs($other);
        $this->putJson("/api/reviews/{$reviewId}", [
            'rating' => 1,
            'comment' => 'Чужая правка',
        ])->assertNotFound();

        $unfinished = $this->purchasedItem($other, $product, Order::STATUS_PENDING);
        $this->postJson("/api/products/{$product->id}/reviews", [
            'order_product_id' => $unfinished->id,
            'rating' => 5,
        ])->assertConflict();
    }

    public function test_customer_edit_does_not_restore_hidden_review(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $item = $this->purchasedItem($customer, $product, Order::STATUS_COMPLETED);
        $review = Review::query()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'order_product_id' => $item->id,
            'rating' => 3,
            'comment' => 'Первый текст',
            'status' => Review::STATUS_HIDDEN,
        ]);

        Sanctum::actingAs($customer);
        $this->putJson("/api/reviews/{$review->id}", [
            'rating' => 4,
            'comment' => 'Исправленный текст',
        ])->assertOk()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.status', Review::STATUS_HIDDEN);

        $review->refresh();
        $this->assertSame('Исправленный текст', $review->comment);
        $this->assertNotNull($review->customer_edited_at);
        $this->assertSame(Review::STATUS_HIDDEN, $review->status);
        $this->getJson("/api/products/{$product->id}/reviews")
            ->assertJsonPath('summary.count', 0)
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/reviews/mine')
            ->assertJsonPath('data.0.id', $review->id)
            ->assertJsonPath('data.0.status', Review::STATUS_HIDDEN);
    }

    public function test_manager_hides_and_restores_without_rewriting_customer_text(): void
    {
        $manager = $this->manager();
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $item = $this->purchasedItem($customer, $product, Order::STATUS_DELIVERED);
        $review = Review::query()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'order_product_id' => $item->id,
            'rating' => 2,
            'comment' => 'Неизменяемый текст клиента',
            'status' => Review::STATUS_PUBLISHED,
        ]);

        Sanctum::actingAs($manager);
        $this->postJson("/api/manager/reviews/{$review->id}/hide", [
            'reason_code' => ContentModerationLog::REASON_ABUSE,
        ])->assertUnprocessable();

        $payload = [
            'reason_code' => ContentModerationLog::REASON_OFF_TOPIC,
            'comment' => 'Отзыв относится к доставке, а не к товару',
            'rating' => 5,
            'customer_comment' => 'Попытка переписать',
        ];
        $this->postJson("/api/manager/reviews/{$review->id}/hide", $payload)
            ->assertOk()
            ->assertJsonPath('data.status', Review::STATUS_HIDDEN)
            ->assertJsonPath('data.actions.can_edit_customer_text', false);
        $this->postJson("/api/manager/reviews/{$review->id}/hide", $payload)
            ->assertOk();

        $review->refresh();
        $this->assertSame(2, $review->rating);
        $this->assertSame('Неизменяемый текст клиента', $review->comment);
        $this->assertSame(1, $review->moderationLogs()->count());
        $this->getJson("/api/products/{$product->id}/reviews")
            ->assertJsonPath('summary.count', 0);

        $this->postJson("/api/manager/reviews/{$review->id}/restore", [
            'comment' => 'Проверили контекст и восстановили',
        ])->assertOk()->assertJsonPath('data.status', Review::STATUS_PUBLISHED);
        $this->assertSame(2, $review->moderationLogs()->count());
        $this->getJson("/api/products/{$product->id}/reviews")
            ->assertJsonPath('summary.average', 2)
            ->assertJsonPath('summary.count', 1);
    }

    public function test_company_has_one_editable_reply_and_customer_text_is_preserved(): void
    {
        $manager = $this->manager();
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $item = $this->purchasedItem($customer, $product, Order::STATUS_DELIVERED);
        $review = Review::query()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'order_product_id' => $item->id,
            'rating' => 5,
            'comment' => 'Спасибо за продукт',
            'status' => Review::STATUS_PUBLISHED,
        ]);

        Sanctum::actingAs($manager);
        $this->putJson("/api/manager/reviews/{$review->id}/reply", [
            'body' => 'Спасибо за ваш отзыв!',
        ])->assertOk()->assertJsonPath(
            'data.company_reply.body',
            'Спасибо за ваш отзыв!'
        );
        $this->putJson("/api/manager/reviews/{$review->id}/reply", [
            'body' => 'Спасибо, ждём вас снова!',
        ])->assertOk()->assertJsonPath(
            'data.company_reply.body',
            'Спасибо, ждём вас снова!'
        );

        $this->assertSame(1, ReviewReply::query()->where('review_id', $review->id)->count());
        $this->assertNotNull($review->reply()->first()->edited_at);
        $this->assertSame('Спасибо за продукт', $review->fresh()->comment);

        $this->getJson("/api/products/{$product->id}/reviews")
            ->assertJsonPath('data.0.company_reply.author_name', $manager->name)
            ->assertJsonPath('data.0.company_reply.body', 'Спасибо, ждём вас снова!');
    }

    public function test_order_feedback_is_separate_from_product_rating(): void
    {
        $manager = $this->manager();
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $item = $this->purchasedItem($customer, $product, Order::STATUS_DELIVERED);
        $order = $item->order;

        Sanctum::actingAs($customer);
        $response = $this->postJson("/api/orders/{$order->id}/feedback", [
            'delivery_rating' => 2,
            'packing_rating' => 4,
            'service_rating' => 5,
            'comment' => 'Товар хороший, доставка задержалась',
        ])->assertCreated()
            ->assertJsonPath('data.ratings.delivery', 2)
            ->assertJsonPath('data.ratings.packing', 4)
            ->assertJsonPath('data.ratings.service', 5);
        $feedbackId = (int) $response->json('data.id');

        $this->getJson("/api/products/{$product->id}/reviews")
            ->assertJsonPath('summary.average', null)
            ->assertJsonPath('summary.count', 0);
        $this->postJson("/api/orders/{$order->id}/feedback", [
            'delivery_rating' => 5,
        ])->assertConflict();

        Sanctum::actingAs($manager);
        $this->getJson('/api/manager/order-feedback')
            ->assertOk()
            ->assertJsonPath('summary.delivery.average', 2)
            ->assertJsonPath('summary.packing.average', 4)
            ->assertJsonPath('summary.service.average', 5);
        $this->postJson("/api/manager/order-feedback/{$feedbackId}/hide", [
            'reason_code' => ContentModerationLog::REASON_ABUSE,
            'comment' => 'Содержит нарушение',
        ])->assertOk()->assertJsonPath('data.status', OrderFeedback::STATUS_HIDDEN);
        $this->getJson('/api/manager/order-feedback')
            ->assertJsonPath('summary.delivery.average', null)
            ->assertJsonPath('summary.delivery.count', 0);
    }

    private function purchasedItem(
        User $customer,
        Product $product,
        string $status
    ): OrderProduct {
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'sales_channel' => Order::SALES_CHANNEL_ONLINE,
            'status' => $status,
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

    private function manager(): User
    {
        $manager = User::factory()->create();
        $manager->assignRole(User::ROLE_MANAGER);

        return $manager;
    }
}
