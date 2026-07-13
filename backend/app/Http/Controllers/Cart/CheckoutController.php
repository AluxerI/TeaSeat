<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    protected $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * Оформить заказ
     */
    public function __invoke(Request $request)
    {
        $request->merge([
            'idempotency_key' => $request->header('Idempotency-Key'),
        ]);

        $request->validate([
            'shipping_address_id' => 'required|exists:address_client,id',
            'delivery_method_id' => 'required|exists:delivery_methods,id',
            'payment_method' => 'required|in:cash,card,online',
            'customer_notes' => 'nullable|string|max:500',
            'is_supplier_order' => 'boolean',
            'idempotency_key' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'regex:/^[A-Za-z0-9._:-]+$/',
            ],
        ]);

        try {
            $userId = Auth::id();
            
            $order = $this->checkoutService->checkout(
                $userId,
                $request->shipping_address_id,
                $request->delivery_method_id,
                $request->payment_method,
                $request->customer_notes,
                $request->boolean('is_supplier_order'),
                $request->idempotency_key,
            );

            return new OrderResource($order);

        } catch (\Exception $e) {
            Log::error('Checkout error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при оформлении заказа',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 422);
        }
    }

    /**
     * Получить доступные способы доставки для адреса
     */
    public function getDeliveryMethods(Request $request, int $addressId)
    {
        try {
            $userId = Auth::id();
            $methods = $this->checkoutService->getAvailableDeliveryMethods($userId, $addressId);

            return response()->json([
                'data' => $methods
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при получении способов доставки',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
