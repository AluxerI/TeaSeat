<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderFeedbackResource;
use App\Services\ReviewService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderFeedbackController extends Controller
{
    public function __construct(protected ReviewService $reviewService)
    {
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $feedback = $this->reviewService->getOwnFeedback($request->user(), $order);

        return response()->json(['data' => new OrderFeedbackResource($feedback)]);
    }

    public function store(Request $request, int $order): JsonResponse
    {
        $validated = $this->validateFeedback($request);
        try {
            $feedback = $this->reviewService->createFeedback(
                $request->user(),
                $order,
                $validated
            );
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        }

        return response()->json([
            'data' => new OrderFeedbackResource($feedback),
        ], 201);
    }

    public function update(Request $request, int $feedback): JsonResponse
    {
        $validated = $this->validateFeedback($request);
        try {
            $item = $this->reviewService->updateFeedback(
                $request->user(),
                $feedback,
                $validated
            );
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        }

        return response()->json(['data' => new OrderFeedbackResource($item)]);
    }

    /** @return array<string, mixed> */
    private function validateFeedback(Request $request): array
    {
        return $request->validate([
            'delivery_rating' => ['nullable', 'integer', 'between:1,5', 'required_without_all:packing_rating,service_rating'],
            'packing_rating' => ['nullable', 'integer', 'between:1,5', 'required_without_all:delivery_rating,service_rating'],
            'service_rating' => ['nullable', 'integer', 'between:1,5', 'required_without_all:delivery_rating,packing_rating'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function rejected(DomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'order_feedback_rejected',
        ], 409);
    }
}
