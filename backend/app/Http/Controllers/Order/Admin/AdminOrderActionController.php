<?php

namespace App\Http\Controllers\Order\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\AdminOrderResource;
use Illuminate\Support\Facades\Auth;
use App\Services\OrderManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminOrderActionController extends Controller
{
    public function __construct(protected OrderManagementService $orderService) 
    {
        $this->middleware(['auth:sanctum', 'permission:manage orders']);
    }

    /**
     * Отменить заказ
     */
    public function cancel(Order $order, Request $request): JsonResponse
    {
        $managerId = Auth::id();
        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        $cancelledOrder = $this->orderService->cancelOrderByManager(
            $order, 
            $request->reason,
            $managerId
        );

        return response()->json([
            'message' => 'Заказ отменен',
            'data' => new AdminOrderResource($cancelledOrder)
        ]);
    }

    /**
     * Подтвердить заказ
     */
    public function confirm(Order $order): JsonResponse
    {
        $managerId = Auth::id();
        $confirmedOrder = $this->orderService->confirmOrder($order, $managerId);

        return response()->json([
            'message' => 'Заказ подтвержден',
            'data' => new AdminOrderResource($confirmedOrder)
        ]);
    }

    /**
     * Отметить как отправленный
     */
    public function markAsShipped(Order $order): JsonResponse
    {
        $managerId = Auth::id();
        $shippedOrder = $this->orderService->markAsShipped($order, $managerId);

        return response()->json([
            'message' => 'Заказ отмечен как отправленный',
            'data' => new AdminOrderResource($shippedOrder)
        ]);
    }

    /**
     * Отметить как доставленный
     */
    public function markAsDelivered(Order $order): JsonResponse
    {
        $managerId = Auth::id();
        $deliveredOrder = $this->orderService->markAsDelivered($order, $managerId);

        return response()->json([
            'message' => 'Заказ отмечен как доставленный',
            'data' => new AdminOrderResource($deliveredOrder)
        ]);
    }

    /**
     * Обновить способ доставки
     */
    public function updateDeliveryMethod(Order $order, Request $request): JsonResponse
    {
        $request->validate([
            'delivery_method_id' => 'required|exists:delivery_methods,id',
            'shipping_cost' => 'nullable|numeric|min:0'
        ]);

        $updatedOrder = $this->orderService->updateDeliveryMethod(
            $order,
            $request->delivery_method_id,
            $request->shipping_cost
        );

        return response()->json([
            'message' => 'Способ доставки обновлен',
            'data' => new AdminOrderResource($updatedOrder)
        ]);
    }
}