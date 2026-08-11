<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService)
    {
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $reviews = $this->reviewService->publicReviews(
            $product,
            isset($validated['rating']) ? (int) $validated['rating'] : null,
            (int) ($validated['per_page'] ?? 20)
        );

        return response()->json([
            'data' => ReviewResource::collection($reviews->items()),
            'summary' => $this->reviewService->productRatingSummary($product),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }
}
