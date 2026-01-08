<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CartResource;

use Illuminate\Support\Facades\Log;

class IndexController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Получить корзину текущего пользователя
     */
    public function __invoke()
    {
        try {
            $userId = Auth::id();
            $cart = $this->cartService->getCart($userId);
            return new CartResource($cart);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении корзины', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при получении корзины',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
