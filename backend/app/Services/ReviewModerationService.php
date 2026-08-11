<?php

namespace App\Services;

use App\Models\ContentModerationLog;
use App\Models\OrderFeedback;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReviewModerationService
{
    public function __construct(protected ManagerAccessService $accessService)
    {
    }

    /** @param array<string, mixed> $filters */
    public function reviews(User $manager, array $filters): LengthAwarePaginator
    {
        $this->accessService->assertManager($manager);

        return Review::query()
            ->with(['user:id,name,email', 'product:id,name', 'reply', 'latestModeration.moderator:id,name'])
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) =>
                $query->where('status', $filters['status']))
            ->when(isset($filters['rating']), fn ($query) =>
                $query->where('rating', $filters['rating']))
            ->when(isset($filters['product_id']), fn ($query) =>
                $query->where('product_id', $filters['product_id']))
            ->when(isset($filters['search']), function ($query) use ($filters): void {
                $search = '%' . $filters['search'] . '%';
                $query->where(function ($nested) use ($search): void {
                    $nested->where('comment', 'ilike', $search)
                        ->orWhereHas('user', fn ($users) =>
                            $users->where('name', 'ilike', $search))
                        ->orWhereHas('product', fn ($products) =>
                            $products->where('name', 'ilike', $search));
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string, mixed> $filters */
    public function feedback(User $manager, array $filters): LengthAwarePaginator
    {
        $this->accessService->assertManager($manager);

        return OrderFeedback::query()
            ->with(['user:id,name,email', 'order:id,status,created_at', 'latestModeration.moderator:id,name'])
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) =>
                $query->where('status', $filters['status']))
            ->when(isset($filters['search']), function ($query) use ($filters): void {
                $search = '%' . $filters['search'] . '%';
                $query->where(function ($nested) use ($search): void {
                    $nested->where('comment', 'ilike', $search)
                        ->orWhereHas('user', fn ($users) =>
                            $users->where('name', 'ilike', $search));
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function getReview(User $manager, int $reviewId): Review
    {
        $this->accessService->assertManager($manager);

        return Review::query()->with([
            'user:id,name,email', 'product:id,name', 'orderProduct.order:id,status',
            'reply.author:id,name', 'latestModeration.moderator:id,name',
            'moderationLogs.moderator:id,name',
        ])->findOrFail($reviewId);
    }

    public function getFeedback(User $manager, int $feedbackId): OrderFeedback
    {
        $this->accessService->assertManager($manager);

        return OrderFeedback::query()->with([
            'user:id,name,email', 'order:id,status,created_at',
            'latestModeration.moderator:id,name', 'moderationLogs.moderator:id,name',
        ])->findOrFail($feedbackId);
    }

    public function hideReview(
        User $manager,
        int $reviewId,
        string $reasonCode,
        string $comment
    ): Review {
        return $this->moderateReview(
            $manager,
            $reviewId,
            Review::STATUS_HIDDEN,
            ContentModerationLog::ACTION_HIDE,
            $reasonCode,
            $comment
        );
    }

    public function restoreReview(User $manager, int $reviewId, string $comment): Review
    {
        return $this->moderateReview(
            $manager,
            $reviewId,
            Review::STATUS_PUBLISHED,
            ContentModerationLog::ACTION_RESTORE,
            null,
            $comment
        );
    }

    public function hideFeedback(
        User $manager,
        int $feedbackId,
        string $reasonCode,
        string $comment
    ): OrderFeedback {
        return $this->moderateFeedback(
            $manager,
            $feedbackId,
            OrderFeedback::STATUS_HIDDEN,
            ContentModerationLog::ACTION_HIDE,
            $reasonCode,
            $comment
        );
    }

    public function restoreFeedback(
        User $manager,
        int $feedbackId,
        string $comment
    ): OrderFeedback {
        return $this->moderateFeedback(
            $manager,
            $feedbackId,
            OrderFeedback::STATUS_PUBLISHED,
            ContentModerationLog::ACTION_RESTORE,
            null,
            $comment
        );
    }

    public function reply(User $manager, int $reviewId, string $body): Review
    {
        $this->assertModerator($manager);

        DB::transaction(function () use ($manager, $reviewId, $body): void {
            $review = Review::query()->whereKey($reviewId)->lockForUpdate()->firstOrFail();
            $existing = ReviewReply::query()
                ->where('review_id', $review->id)
                ->lockForUpdate()
                ->first();

            ReviewReply::query()->updateOrCreate(
                ['review_id' => $review->id],
                [
                    'author_id' => $manager->id,
                    'author_name' => trim((string) $manager->name),
                    'body' => trim($body),
                    'edited_at' => $existing ? now() : null,
                ]
            );
        });

        return $this->getReview($manager, $reviewId);
    }

    /** @return array<string, array{average:float|null,count:int}> */
    public function feedbackSummary(User $manager): array
    {
        $this->accessService->assertManager($manager);
        $row = OrderFeedback::query()->published()->selectRaw(
            'AVG(delivery_rating) AS delivery_average, COUNT(delivery_rating) AS delivery_count, '
            . 'AVG(packing_rating) AS packing_average, COUNT(packing_rating) AS packing_count, '
            . 'AVG(service_rating) AS service_average, COUNT(service_rating) AS service_count'
        )->first();

        return collect(['delivery', 'packing', 'service'])->mapWithKeys(
            fn (string $category): array => [$category => [
                'average' => $row->{"{$category}_average"} !== null
                    ? round((float) $row->{"{$category}_average"}, 2)
                    : null,
                'count' => (int) $row->{"{$category}_count"},
            ]]
        )->all();
    }

    private function moderateReview(
        User $manager,
        int $reviewId,
        string $status,
        string $action,
        ?string $reasonCode,
        string $comment
    ): Review {
        $this->assertModerator($manager);

        DB::transaction(function () use (
            $manager, $reviewId, $status, $action, $reasonCode, $comment
        ): void {
            $review = Review::query()->whereKey($reviewId)->lockForUpdate()->firstOrFail();
            if ($review->status === $status) {
                return;
            }
            $review->update(['status' => $status]);
            $this->writeLog($manager, $action, $reasonCode, $comment, $review->id, null);
        });

        return $this->getReview($manager, $reviewId);
    }

    private function moderateFeedback(
        User $manager,
        int $feedbackId,
        string $status,
        string $action,
        ?string $reasonCode,
        string $comment
    ): OrderFeedback {
        $this->assertModerator($manager);

        DB::transaction(function () use (
            $manager, $feedbackId, $status, $action, $reasonCode, $comment
        ): void {
            $feedback = OrderFeedback::query()
                ->whereKey($feedbackId)->lockForUpdate()->firstOrFail();
            if ($feedback->status === $status) {
                return;
            }
            $feedback->update(['status' => $status]);
            $this->writeLog($manager, $action, $reasonCode, $comment, null, $feedback->id);
        });

        return $this->getFeedback($manager, $feedbackId);
    }

    private function writeLog(
        User $manager,
        string $action,
        ?string $reasonCode,
        string $comment,
        ?int $reviewId,
        ?int $feedbackId
    ): void {
        ContentModerationLog::query()->create([
            'review_id' => $reviewId,
            'order_feedback_id' => $feedbackId,
            'moderator_id' => $manager->id,
            'moderator_name' => trim((string) $manager->name),
            'action' => $action,
            'reason_code' => $reasonCode,
            'comment' => trim($comment),
        ]);
    }

    private function assertModerator(User $manager): void
    {
        $this->accessService->assertManager($manager);
        if (!$manager->can('moderate reviews')) {
            throw new AuthorizationException('Нет права модерировать отзывы');
        }
    }
}
