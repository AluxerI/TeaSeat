<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourierDeliveryResource;
use App\Services\DeliveryWorkflowService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryWorkflowService $deliveryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:ready_for_delivery,shipped,awaiting_receipt,delivered'],
            'warehouse_id' => ['nullable', 'integer'],
            'delivery_kind' => ['nullable', 'in:transfer,customer'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $orders = $this->deliveryService->deliveries(
                $request->user(),
                $filters
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        }

        return response()->json([
            'data' => CourierDeliveryResource::collection($orders->items()),
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
            $item = $this->deliveryService->findAccessible(
                $request->user(),
                $order
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Доставка не найдена'], 404);
        }

        return response()->json([
            'data' => new CourierDeliveryResource($item),
        ]);
    }

    public function claim(Request $request, int $order): JsonResponse
    {
        return $this->transition($request, $order, 'claim', 'Доставка назначена курьеру');
    }

    public function release(Request $request, int $order): JsonResponse
    {
        $request->merge([
            'reason' => trim((string) $request->input('reason')),
        ]);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->transition(
            $request,
            $order,
            'release',
            'Доставка возвращена в очередь',
            [$validated['reason']]
        );
    }

    public function start(Request $request, int $order): JsonResponse
    {
        return $this->transition($request, $order, 'start', 'Доставка начата');
    }

    public function deliver(Request $request, int $order): JsonResponse
    {
        return $this->transition($request, $order, 'deliver', 'Доставка завершена');
    }

    private function transition(
        Request $request,
        int $order,
        string $action,
        string $message,
        array $arguments = []
    ): JsonResponse {
        try {
            $item = $this->deliveryService->{$action}(
                $request->user(),
                $order,
                ...$arguments
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Доставка не найдена'], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'delivery_transition_rejected',
            ], 409);
        }

        return response()->json([
            'message' => $message,
            'data' => new CourierDeliveryResource($item),
        ]);
    }

    private function forbidden(AuthorizationException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'courier_access_denied',
        ], 403);
    }
}
