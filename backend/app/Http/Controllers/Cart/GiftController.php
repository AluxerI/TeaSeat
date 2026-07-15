<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Services\GiftCartService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    public function __construct(protected GiftCartService $giftCartService)
    {
    }

    public function store(Request $request): JsonResponse|CartResource
    {
        $data = $request->validate([
            'gift_id' => ['required', 'integer', 'exists:gifts,id'],
            'gift_version' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'client_instance_id' => ['required', 'uuid'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            return new CartResource($this->giftCartService->add(
                $request->user(),
                (int) $data['gift_id'],
                (int) $data['gift_version'],
                (int) $data['quantity'],
                $data['client_instance_id'],
                $data['city'] ?? null
            ));
        } catch (DomainException $exception) {
            return $this->invalid($exception);
        }
    }

    public function update(Request $request, int $orderGift): JsonResponse|CartResource
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            return new CartResource($this->giftCartService->updateQuantity(
                $request->user(),
                $orderGift,
                (int) $data['quantity'],
                $data['city'] ?? null
            ));
        } catch (DomainException $exception) {
            return $this->invalid($exception);
        }
    }

    public function destroy(Request $request, int $orderGift): JsonResponse|CartResource
    {
        return new CartResource(
            $this->giftCartService->remove($request->user(), $orderGift)
        );
    }

    private function invalid(DomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'gift_cart_rejected',
        ], 422);
    }
}
