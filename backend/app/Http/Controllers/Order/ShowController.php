<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Log;

class ShowController extends Controller
{
    public function __invoke(Order $order)
    {
        try {
            if ($order->user_id !== Auth::id()
                || $order->sales_channel !== Order::SALES_CHANNEL_ONLINE) {
                return response()->json(['message' => 'Заказ не найден'], 404);
            }

            // Загружаем только то, что нужно пользователю
            $order->load([
                'items.product', 
                'gifts.items.product',
                'deliveryMethod', 
                'shippingAddress',
                // УБРАЛИ: warehouse, partialOrders - это внутренняя информация
            ]);

            return new OrderResource($order);

        } catch (\Exception $e) {
            Log::error('Error fetching order details', [
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при получении деталей заказа',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
