<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\ManagerOrderResource;
use App\Models\Order;
use App\Services\ManagerOrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends ManagerController
{
    public function __construct(
        protected ManagerOrderService $orderService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_values(array_diff(
                array_keys(Order::getStatusName()),
                [Order::STATUS_CART]
            )))],
            'sales_channel' => ['nullable', Rule::in([
                Order::SALES_CHANNEL_ONLINE,
                Order::SALES_CHANNEL_SELLER,
                Order::SALES_CHANNEL_INTERNAL,
            ])],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'has_issue' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $manager = $request->user();
            $this->setAccessContext($request);
            $orders = $this->orderService->orders($manager, $filters);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        }

        return response()->json([
            'data' => ManagerOrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        try {
            $this->setAccessContext($request);
            $item = $this->orderService->findAccessible(
                $request->user(),
                $order
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        return response()->json([
            'data' => new ManagerOrderResource($item),
        ]);
    }

    private function setAccessContext(Request $request): void
    {
        $request->attributes->set(
            'manager_active_warehouse_ids',
            $this->orderService->activeWarehouseIds($request->user())
        );
    }
}
