<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CartResource;
use Illuminate\Support\Facades\Log;

class AddController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Добавить товар в корзину
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1|max:100',
            'city' => 'nullable|string',
            'is_supplier_order' => 'boolean' 
        ]);

        try {
            $userId = Auth::id();
            $cart = $this->cartService->addItem(
                $userId,
                $request->product_id,
                $request->quantity,
                $request->city ,
                $request->boolean('is_supplier_order')
            );

            return new CartResource($cart);
        } catch (\Exception $e) {
            Log::error('Ошибка при добавлении товара в корзину', [
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при добавлении товара в корзину',
                'error' => $e->getMessage() // Показываем пользователю понятную ошибку
            ], 422);
        }
    }
}
