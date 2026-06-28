<?php

namespace App\Http\Controllers\Wishlist;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistResource;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $user = $request->user();
        $productId = $request->product_id;

        // Проверяем, не добавлен ли уже товар
        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Товар уже в избранном'
            ], 422);
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $productId
        ]);

        // Загружаем связь product для ресурса
        $wishlist->load('product');

        return response()->json([
            'message' => 'Товар добавлен в избранное',
            'data' => new WishlistResource($wishlist)
        ], 201);
    }
}