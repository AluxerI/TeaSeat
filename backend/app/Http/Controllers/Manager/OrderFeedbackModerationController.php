<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\ManagerOrderFeedbackResource;
use App\Models\ContentModerationLog;
use App\Models\OrderFeedback;
use App\Services\ReviewModerationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderFeedbackModerationController extends ManagerController
{
    public function __construct(protected ReviewModerationService $reviewService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                OrderFeedback::STATUS_PUBLISHED, OrderFeedback::STATUS_HIDDEN, 'all',
            ])],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $feedback = $this->reviewService->feedback($request->user(), $filters);
            $summary = $this->reviewService->feedbackSummary($request->user());
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        }

        return response()->json([
            'data' => ManagerOrderFeedbackResource::collection($feedback->items()),
            'summary' => $summary,
            'meta' => [
                'current_page' => $feedback->currentPage(),
                'last_page' => $feedback->lastPage(),
                'per_page' => $feedback->perPage(),
                'total' => $feedback->total(),
            ],
        ]);
    }

    public function show(Request $request, int $feedback): JsonResponse
    {
        try {
            $item = $this->reviewService->getFeedback($request->user(), $feedback);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Оценка заказа не найдена'], 404);
        }

        return response()->json(['data' => new ManagerOrderFeedbackResource($item)]);
    }

    public function hide(Request $request, int $feedback): JsonResponse
    {
        $validated = $request->validate([
            'reason_code' => ['required', Rule::in(array_keys(
                ContentModerationLog::reasonOptions()
            ))],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        return $this->execute($request, fn () => $this->reviewService->hideFeedback(
            $request->user(),
            $feedback,
            $validated['reason_code'],
            $validated['comment']
        ), 'Оценка заказа скрыта');
    }

    public function restore(Request $request, int $feedback): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        return $this->execute($request, fn () => $this->reviewService->restoreFeedback(
            $request->user(), $feedback, $validated['comment']
        ), 'Оценка заказа восстановлена');
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
            return response()->json(['message' => 'Оценка заказа не найдена'], 404);
        }

        return response()->json([
            'message' => $message,
            'data' => new ManagerOrderFeedbackResource($item),
        ]);
    }
}
