<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Services\ReviewService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService)
    {
    }

    public function mine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $reviews = $this->reviewService->ownReviews(
            $request->user(),
            (int) ($validated['per_page'] ?? 20)
        );

        return response()->json([
            'data' => ReviewResource::collection($reviews->items()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $this->validatedReview($request, true);

        try {
            $review = $this->reviewService->createReview(
                $request->user(),
                $product,
                (int) $validated['order_product_id'],
                (int) $validated['rating'],
                $validated['comment'] ?? null
            );
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        }

        return response()->json(['data' => new ReviewResource($review)], 201);
    }

    public function update(Request $request, int $review): JsonResponse
    {
        $validated = $this->validatedReview($request, false);

        try {
            $item = $this->reviewService->updateReview(
                $request->user(),
                $review,
                (int) $validated['rating'],
                $validated['comment'] ?? null
            );
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        }

        return response()->json(['data' => new ReviewResource($item)]);
    }

    /** @return array<string, mixed> */
    private function validatedReview(Request $request, bool $creating): array
    {
        $rules = [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
        if ($creating) {
            $rules['order_product_id'] = ['required', 'integer', 'exists:order_products,id'];
        }

        return $request->validate($rules);
    }

    private function rejected(DomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'review_rejected',
        ], 409);
    }
}
