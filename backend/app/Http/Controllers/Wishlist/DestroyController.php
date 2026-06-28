<?php

namespace App\Http\Controllers\Wishlist;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __invoke(Request $request, $productId)
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Товар не найден в избранном'
            ], 404);
        }

        return response()->json([
            'message' => 'Товар удален из избранного'
        ]);
    }
}