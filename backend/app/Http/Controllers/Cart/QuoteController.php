<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Models\AddressClient;
use App\Models\DeliveryMethod;
use App\Models\Discount;
use App\Models\User;
use App\Services\CartService;
use App\Services\CartSelectionService;
use App\Services\PricingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected PricingService $pricingService,
        protected CartSelectionService $cartSelectionService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_method_id' => 'nullable|integer|exists:delivery_methods,id',
            'shipping_address_id' => 'required_with:delivery_method_id|integer|exists:address_client,id',
            'discount_selection' => 'nullable|array',
            'discount_selection.type' => 'required_with:discount_selection|in:personal,coupon',
            'discount_selection.discount_id' => 'required_if:discount_selection.type,personal|prohibited_unless:discount_selection.type,personal|integer|exists:discounts,id',
            'discount_selection.code' => 'required_if:discount_selection.type,coupon|prohibited_unless:discount_selection.type,coupon|string|max:100',
            'cart_item_ids' => 'sometimes|array',
            'cart_item_ids.*' => 'integer|distinct|min:1',
            'cart_gift_ids' => 'sometimes|array',
            'cart_gift_ids.*' => 'integer|distinct|min:1',
        ]);

        $user = User::findOrFail($request->user()->id);
        $cart = $this->cartService->getCart($user->id);
        $shippingCost = 0.0;

        try {
            $selection = $validated['discount_selection'] ?? null;
            if (($selection['type'] ?? null) === 'coupon'
                && empty($validated['delivery_method_id'])) {
                $coupon = Discount::query()
                    ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($selection['code']))])
                    ->first();

                if ($coupon?->type === Discount::TYPE_SHIPPING) {
                    throw new DomainException(
                        'Для промокода на доставку выберите адрес и способ доставки'
                    );
                }
            }

            if (!empty($validated['delivery_method_id'])) {
                $deliveryMethod = DeliveryMethod::active()->findOrFail($validated['delivery_method_id']);
                $address = AddressClient::where('user_id', $user->id)
                    ->findOrFail($validated['shipping_address_id']);

                if (!$deliveryMethod->isAvailableInCity($address->city)) {
                    throw new DomainException(
                        "Способ доставки «{$deliveryMethod->name}» недоступен в городе {$address->city}"
                    );
                }

                $shippingCost = (float) $deliveryMethod->cost;
            }

            $resolved = $this->cartSelectionService->resolve(
                $cart,
                array_key_exists('cart_item_ids', $validated)
                    ? $validated['cart_item_ids']
                    : null,
                array_key_exists('cart_gift_ids', $validated)
                    ? $validated['cart_gift_ids']
                    : null,
            );
            $quote = $this->pricingService->quoteOrder(
                $resolved['order'],
                $user,
                $shippingCost,
                $selection
            );
            $quote['cart_selection'] = [
                'explicit' => $resolved['explicit'],
                'cart_item_ids' => $resolved['item_ids'],
                'cart_gift_ids' => $resolved['gift_ids'],
            ];
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $quote]);
    }
}
