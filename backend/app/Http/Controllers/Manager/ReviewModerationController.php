<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\ManagerReviewResource;
use App\Models\ContentModerationLog;
use App\Models\Review;
use App\Services\ReviewModerationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewModerationController extends ManagerController
{
    public function __construct(protected ReviewModerationService $reviewService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                Review::STATUS_PUBLISHED, Review::STATUS_HIDDEN, 'all',
            ])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $reviews = $this->reviewService->reviews($request->user(), $filters);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        }

        return response()->json([
            'data' => ManagerReviewResource::collection($reviews->items()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function show(Request $request, int $review): JsonResponse
    {
        return $this->find($request, $review);
    }

    public function hide(Request $request, int $review): JsonResponse
    {
        $validated = $request->validate([
            'reason_code' => ['required', Rule::in(array_keys(
                ContentModerationLog::reasonOptions()
            ))],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        return $this->execute($request, function () use ($request, $review, $validated) {
            return $this->reviewService->hideReview(
                $request->user(),
                $review,
                $validated['reason_code'],
                $validated['comment']
            );
        }, 'Отзыв скрыт');
    }

    public function restore(Request $request, int $review): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        return $this->execute($request, fn () => $this->reviewService->restoreReview(
            $request->user(), $review, $validated['comment']
        ), 'Отзыв восстановлен');
    }

    public function reply(Request $request, int $review): JsonResponse
    {
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        return $this->execute($request, fn () => $this->reviewService->reply(
            $request->user(), $review, $validated['body']
        ), 'Ответ компании сохранён');
    }

    private function find(Request $request, int $review): JsonResponse
    {
        try {
            $item = $this->reviewService->getReview($request->user(), $review);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Отзыв не найден'], 404);
        }

        return response()->json(['data' => new ManagerReviewResource($item)]);
    }

    private function execute(
        Request $request,
        callable $action,
        string $message
    ): JsonResponse {
        try {
            $item = $action();
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Отзыв не найден'], 404);
        }

        return response()->json([
            'message' => $message,
            'data' => new ManagerReviewResource($item),
        ]);
    }
}
