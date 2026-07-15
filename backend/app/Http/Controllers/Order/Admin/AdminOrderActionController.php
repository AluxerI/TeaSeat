<?php

namespace App\Http\Controllers\Order\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\AdminOrderResource;
use Illuminate\Support\Facades\Auth;
use App\Services\OrderManagementService;
use DomainException;
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

        try {
            $cancelledOrder = $this->orderService->cancelOrderByManager(
                $order,
                $request->reason,
                $managerId
            );
        } catch (DomainException $exception) {
            return $this->transitionRejected($exception);
        }

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
        try {
            $confirmedOrder = $this->orderService->confirmOrder($order, $managerId);
        } catch (DomainException $exception) {
            return $this->transitionRejected($exception);
        }

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
        try {
            $shippedOrder = $this->orderService->markAsShipped($order, $managerId);
        } catch (DomainException $exception) {
            return $this->transitionRejected($exception);
        }

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
        try {
            $deliveredOrder = $this->orderService->markAsDelivered($order, $managerId);
        } catch (DomainException $exception) {
            return $this->transitionRejected($exception);
        }

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

    private function transitionRejected(DomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'order_transition_rejected',
        ], 409);
    }
}
