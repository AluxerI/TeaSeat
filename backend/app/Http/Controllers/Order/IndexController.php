<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Log;

class IndexController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $userId = Auth::id();
            
            $orders = Order::with(['items.product', 'deliveryMethod', 'shippingAddress'])
                ->where('user_id', $userId)
                ->realOrders()
                // Показываем только основные заказы (не частичные)
                ->whereNull('parent_order_id')
                ->orderBy('created_at', 'desc')
                ->get(); // Убрал paginate()

            return OrderResource::collection($orders);

        } catch (\Exception $e) {
            Log::error('Error fetching orders', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Ошибка при получении заказов',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}