<?php

namespace App\Http\Controllers\Order\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Resources\AdminOrderResource;
use App\Services\OrderManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminOrderController extends Controller
{
    public function __construct(protected OrderManagementService $orderService) 
    {
        $this->middleware(['auth:sanctum', 'permission:manage orders']);
    }

    /**
     * Список заказов с фильтрацией и пагинацией
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->getOrdersWithFilters($request);
        
        return response()->json([
            'data' => AdminOrderResource::collection($orders),
            'meta' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
            ]
        ]);
    }

    /**
     * Детальная информация о заказе
     */
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'items.product', 
            'deliveryMethod', 
            'shippingAddress',
            'warehouse',
            'user',
            'partialOrders.items.product',
            'partialOrders.warehouse',
            'supplierOrder.supplier',
            'statusHistory.changedBy'
        ]);

        return response()->json([
            'data' => new AdminOrderResource($order)
        ]);
    }

    /**
     * Обновить статус заказа
     */
    public function updateStatus(Order $order, Request $request): JsonResponse
    {
        if ($order->sales_channel === Order::SALES_CHANNEL_SELLER) {
            return response()->json([
                'message' => 'Статусы продажи продавца меняются только через PWA и будущий workflow проблем комплектации.',
            ], 422);
        }

        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'internal_notes' => 'nullable|string|max:1000'
        ]);

        $updatedOrder = $this->orderService->updateOrderStatus(
            $order, 
            $request->status, 
            $request->internal_notes,
            Auth::id() 
        );

        return response()->json([
            'message' => 'Статус заказа обновлен',
            'data' => new AdminOrderResource($updatedOrder)
        ]);
    }

    /**
     * Обновить трек-номер
     */
    public function updateTracking(Order $order, Request $request): JsonResponse
    {
        $request->validate([
            'tracking_number' => 'required|string|max:100',
            'carrier' => 'nullable|string|max:100'
        ]);

        $updatedOrder = $this->orderService->updateTrackingNumber(
            $order, 
            $request->tracking_number,
            $request->carrier
        );

        return response()->json([
            'message' => 'Трек-номер обновлен',
            'data' => new AdminOrderResource($updatedOrder)
        ]);
    }

    /**
     * Обновить внутренние заметки
     */
    public function updateInternalNotes(Order $order, Request $request): JsonResponse
    {
        $request->validate([
            'internal_notes' => 'required|string|max:2000'
        ]);

        $order->update(['internal_notes' => $request->internal_notes]);

        return response()->json([
            'message' => 'Внутренние заметки обновлены',
            'data' => new AdminOrderResource($order->fresh())
        ]);
    }

    /**
     * Получить статистику заказов
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->orderService->getOrderStats($request);
        
        return response()->json([
            'data' => $stats
        ]);
    }
}
