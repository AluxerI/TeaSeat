<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderFeedback;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function publicReviews(
        Product $product,
        ?int $rating,
        int $perPage
    ): LengthAwarePaginator {
        return $product->reviews()
            ->published()
            ->with(['user:id,name', 'reply:id,review_id,author_name,body,edited_at,created_at'])
            ->when($rating !== null, fn ($query) => $query->where('rating', $rating))
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }

    /** @return array{average:float|null,count:int,distribution:array<int,int>} */
    public function productRatingSummary(Product $product): array
    {
        $ratings = $product->reviews()
            ->published()
            ->selectRaw('rating, COUNT(*) AS aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $distribution = [];
        $total = 0;
        $sum = 0;
        for ($rating = 1; $rating <= 5; $rating++) {
            $count = (int) ($ratings[$rating] ?? 0);
            $distribution[$rating] = $count;
            $total += $count;
            $sum += $rating * $count;
        }

        return [
            'average' => $total > 0 ? round($sum / $total, 2) : null,
            'count' => $total,
            'distribution' => $distribution,
        ];
    }

    public function ownReviews(User $user, int $perPage): LengthAwarePaginator
    {
        return $user->reviews()
            ->with(['product:id,name', 'reply:id,review_id,author_name,body,edited_at,created_at'])
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }

    public function createReview(
        User $user,
        Product $product,
        int $orderProductId,
        int $rating,
        ?string $comment
    ): Review {
        return DB::transaction(function () use (
            $user,
            $product,
            $orderProductId,
            $rating,
            $comment
        ): Review {
            // Сериализуем параллельные попытки одного покупателя. Блокировка
            // строки заказа недостаточна, если товар покупался несколько раз.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            /** @var OrderProduct|null $item */
            $item = OrderProduct::query()
                ->with('order.parentOrder')
                ->whereKey($orderProductId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();
            if (!$item) {
                $this->notFound(OrderProduct::class, $orderProductId);
            }

            $order = $item->order?->parentOrder ?: $item->order;
            if (!$order || (int) $order->user_id !== (int) $user->id) {
                $this->notFound(OrderProduct::class, $orderProductId);
            }
            $this->assertFinishedCustomerOrder($order);

            if (Review::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->exists()) {
                throw new DomainException('Вы уже оставили отзыв об этом товаре');
            }

            return Review::query()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'order_product_id' => $item->id,
                'rating' => $rating,
                'comment' => $comment,
                'status' => Review::STATUS_PUBLISHED,
            ])->load(['user:id,name', 'product:id,name', 'reply']);
        });
    }

    public function updateReview(
        User $user,
        int $reviewId,
        int $rating,
        ?string $comment
    ): Review {
        return DB::transaction(function () use (
            $user,
            $reviewId,
            $rating,
            $comment
        ): Review {
            /** @var Review|null $review */
            $review = Review::query()
                ->whereKey($reviewId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if (!$review) {
                $this->notFound(Review::class, $reviewId);
            }

            $review->update([
                'rating' => $rating,
                'comment' => $comment,
                'customer_edited_at' => now(),
            ]);

            return $review->fresh(['user:id,name', 'product:id,name', 'reply']);
        });
    }

    public function getOwnFeedback(User $user, int $orderId): OrderFeedback
    {
        /** @var OrderFeedback|null $feedback */
        $feedback = OrderFeedback::query()
            ->where('order_id', $orderId)
            ->where('user_id', $user->id)
            ->first();
        if (!$feedback) {
            $this->notFound(OrderFeedback::class, $orderId);
        }

        return $feedback;
    }

    /** @param array<string, mixed> $data */
    public function createFeedback(User $user, int $orderId, array $data): OrderFeedback
    {
        return DB::transaction(function () use ($user, $orderId, $data): OrderFeedback {
            $order = $this->lockOwnRootOrder($user, $orderId);
            $this->assertFinishedCustomerOrder($order);

            if (OrderFeedback::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->exists()) {
                throw new DomainException('Отзыв о качестве этого заказа уже создан');
            }

            return OrderFeedback::query()->create($this->feedbackValues($data) + [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => OrderFeedback::STATUS_PUBLISHED,
            ])->load('order:id,status');
        });
    }

    /** @param array<string, mixed> $data */
    public function updateFeedback(
        User $user,
        int $feedbackId,
        array $data
    ): OrderFeedback {
        return DB::transaction(function () use ($user, $feedbackId, $data): OrderFeedback {
            /** @var OrderFeedback|null $feedback */
            $feedback = OrderFeedback::query()
                ->whereKey($feedbackId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if (!$feedback) {
                $this->notFound(OrderFeedback::class, $feedbackId);
            }

            $feedback->update($this->feedbackValues($data) + [
                'customer_edited_at' => now(),
            ]);

            return $feedback->fresh('order:id,status');
        });
    }

    private function lockOwnRootOrder(User $user, int $orderId): Order
    {
        /** @var Order|null $order */
        $order = Order::query()
            ->whereKey($orderId)
            ->where('user_id', $user->id)
            ->whereNull('parent_order_id')
            ->lockForUpdate()
            ->first();
        if (!$order) {
            $this->notFound(Order::class, $orderId);
        }

        return $order;
    }

    private function assertFinishedCustomerOrder(Order $order): void
    {
        if ($order->sales_channel !== Order::SALES_CHANNEL_ONLINE
            || $order->is_supplier_order
            || !in_array($order->status, [
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ], true)) {
            throw new DomainException(
                'Отзыв можно оставить только после получения интернет-заказа'
            );
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function feedbackValues(array $data): array
    {
        return [
            'delivery_rating' => $data['delivery_rating'] ?? null,
            'packing_rating' => $data['packing_rating'] ?? null,
            'service_rating' => $data['service_rating'] ?? null,
            'comment' => $data['comment'] ?? null,
        ];
    }

    /** @return never */
    private function notFound(string $model, int $id): void
    {
        throw (new ModelNotFoundException())->setModel($model, [$id]);
    }
}
