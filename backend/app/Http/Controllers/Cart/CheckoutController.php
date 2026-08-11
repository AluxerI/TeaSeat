<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Log;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
            'scheduled_delivery_date' => 'nullable|date_format:Y-m-d',
            'delivery_time_slot_id' => 'nullable|integer|exists:delivery_time_slots,id',
            'payment_method' => 'required|in:cash,card,online',
            'customer_notes' => 'nullable|string|max:500',
            'is_supplier_order' => 'boolean',
            'discount_selection' => 'nullable|array',
            'discount_selection.type' => 'required_with:discount_selection|in:personal,coupon',
            'discount_selection.discount_id' => 'required_if:discount_selection.type,personal|prohibited_unless:discount_selection.type,personal|integer|exists:discounts,id',
            'discount_selection.code' => 'required_if:discount_selection.type,coupon|prohibited_unless:discount_selection.type,coupon|string|max:100',
            'cart_item_ids' => 'sometimes|array',
            'cart_item_ids.*' => 'integer|distinct|min:1',
            'cart_gift_ids' => 'sometimes|array',
            'cart_gift_ids.*' => 'integer|distinct|min:1',
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
                $request->input('discount_selection'),
                $request->input('scheduled_delivery_date'),
                $request->integer('delivery_time_slot_id') ?: null,
                $request->has('cart_item_ids')
                    ? $request->input('cart_item_ids', [])
                    : null,
                $request->has('cart_gift_ids')
                    ? $request->input('cart_gift_ids', [])
                    : null,
            );

            return new OrderResource($order);

        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Некорректный адрес, способ доставки или товар',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Checkout error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при оформлении заказа',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
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

    public function getDeliverySlots(
        Request $request,
        int $addressId,
        int $deliveryMethodId
    ) {
        try {
            $result = $this->checkoutService->getAvailableDeliverySlots(
                Auth::id(),
                $addressId,
                $deliveryMethodId
            );

            return response()->json(['data' => $result]);
        } catch (DomainException|ModelNotFoundException $exception) {
            return response()->json([
                'message' => $exception instanceof DomainException
                    ? $exception->getMessage()
                    : 'Некорректный адрес или способ доставки',
            ], 422);
        }
    }
}
