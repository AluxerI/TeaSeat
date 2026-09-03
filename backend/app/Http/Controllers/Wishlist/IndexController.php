<?php

namespace App\Http\Controllers\Wishlist;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistResource;
use App\Models\Wishlist;
use App\Services\ProductRatingService;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    public function __construct(
        private ProductRatingService $productRatingService
    ) {
    }

    public function __invoke(Request $request)
    {
        $wishlist = Wishlist::with([
            'product' => function ($relation): void {
                $this->productRatingService->withPublicAggregates(
                    $relation->getQuery()
                );
            },
        ])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => WishlistResource::collection($wishlist),
            'count' => $wishlist->count()
        ]);
    }
}
