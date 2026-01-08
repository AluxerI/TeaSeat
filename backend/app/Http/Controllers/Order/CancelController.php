<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

            if (!$order->canBeCancelled()) {
                return response()->json([
                    'message' => 'Невозможно отменить заказ в текущем статусе'
                ], 422);
            }

            // Отменяем ВСЕ связанные заказы (основной + частичные)
            $cancelledOrders = DB::transaction(function () use ($order) {
                $allOrders = [];
                
                // Отменяем основной заказ
                $allOrders[] = $this->checkoutService->cancelOrder(Auth::id(), $order->id);
                
                // Отменяем все частичные заказы
                $partialOrders = Order::where('parent_order_id', $order->id)->get();
                foreach ($partialOrders as $partialOrder) {
                    $allOrders[] = $this->checkoutService->cancelOrder(Auth::id(), $partialOrder->id);
                }
                
                return $allOrders;
            });

            return new OrderResource($cancelledOrders[0]); // Возвращаем основной заказ

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