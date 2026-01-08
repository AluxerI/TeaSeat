<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CartResource;
use Illuminate\Support\Facades\Log;

class RemoveItemController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Удалить товар из корзины
     */
    public function __invoke($itemId)
    {
        try {
            $userId = Auth::id();
            $cart = $this->cartService->removeItem($userId, $itemId);
            return new CartResource($cart);
        } catch (\Exception $e) {
            Log::error('Ошибка при удалении товара из корзины', [
                'user_id' => Auth::id(),
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при удалении товара из корзины',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
