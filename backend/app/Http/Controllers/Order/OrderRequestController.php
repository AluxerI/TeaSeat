<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderRequestResource;
use App\Models\OrderRequest;
use App\Services\OrderRequestService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderRequestController extends Controller
{
    public function __construct(protected OrderRequestService $service)
    {
    }

    public function index(Request $request, int $order): JsonResponse
    {
        try {
            $items = $this->service->customerRequests($request->user(), $order);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        return response()->json([
            'data' => OrderRequestResource::collection($items),
        ]);
    }

    public function store(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(OrderRequest::TYPES)],
            'message' => [
                'nullable',
                'string',
                'max:5000',
                'required_if:type,' . OrderRequest::TYPE_ORDER_PROBLEM . ',' . OrderRequest::TYPE_OTHER,
            ],
        ]);

        try {
            $item = $this->service->create(
                $request->user(),
                $order,
                $validated['type'],
                $validated['message'] ?? null
            );
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        }

        return response()->json([
            'data' => new OrderRequestResource($item),
        ], 201);
    }

    public function withdraw(Request $request, int $orderRequest): JsonResponse
    {
        try {
            $item = $this->service->withdraw($request->user(), $orderRequest);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Обращение не найдено'], 404);
        } catch (DomainException $exception) {
            return $this->rejected($exception);
        }

        return response()->json([
            'message' => 'Обращение отозвано',
            'data' => new OrderRequestResource($item),
        ]);
    }

    private function rejected(DomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'order_request_rejected',
        ], 409);
    }
}
