<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CartResource;
use DomainException;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateItemController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Обновить количество товара в корзине
     */
    public function __invoke(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0|max:2147483647'
        ]);

        try {
            $userId = Auth::id();
            $cart = $this->cartService->updateItemQuantity(
                $userId,
                $itemId,
                $request->quantity
            );

            return new CartResource($cart);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Ошибка при обновлении товара в корзине', [
                'user_id' => Auth::id(),
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при обновлении товара в корзине',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
