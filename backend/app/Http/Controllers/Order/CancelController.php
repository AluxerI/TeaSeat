<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Log;

class CancelController extends Controller
{
    protected $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    public function __invoke(Order $order)
    {
        try {
            if ($order->user_id !== Auth::id()) {
                return response()->json(['message' => 'Заказ не найден'], 404);
            }

            $cancelledOrder = $this->checkoutService->cancelOrder(Auth::id(), $order->id);

            return new OrderResource($cancelledOrder);

        } catch (\Exception $e) {
            Log::error('Error cancelling order', [
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при отмене заказа',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
