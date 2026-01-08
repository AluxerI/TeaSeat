<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CartResource;
use Illuminate\Support\Facades\Log;


class ClearCartController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function __invoke(Request $request)
    {
        
        try {
            $userId = Auth::id();
            $cart = $this->cartService->clearCart($userId);

            return new CartResource($cart);

        } catch (\Exception $e) {
            // обработка ошибок
        }
    }
}