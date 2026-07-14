<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Http\Resources\FulfillmentIssueResource;
use App\Http\Resources\PickerOrderResource;
use App\Services\OrderFulfillmentService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderFulfillmentService $fulfillmentService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:confirmed,processing'],
            'warehouse_id' => ['nullable', 'integer'],
            'job_type' => ['nullable', 'in:source,consolidation'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $orders = $this->fulfillmentService->pickingOrders(
                $request->user(),
                $filters
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        }

        return response()->json([
            'data' => PickerOrderResource::collection($orders->items()),
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
            $item = $this->fulfillmentService->findAccessible(
                $request->user(),
                $order
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ для сборки не найден'], 404);
        }

        return response()->json(['data' => new PickerOrderResource($item)]);
    }

    public function incomingTransfers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $orders = $this->fulfillmentService->incomingTransfers(
                $request->user(),
                (int) ($validated['per_page'] ?? 20)
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        }

        return response()->json([
            'data' => PickerOrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function receiveTransfer(Request $request, int $order): JsonResponse
    {
        try {
            $item = $this->fulfillmentService->receiveTransfer(
                $request->user(),
                $order
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Перемещение не найдено'], 404);
        } catch (DomainException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'message' => 'Получение перемещения подтверждено',
            'data' => new PickerOrderResource($item),
        ]);
    }

    public function take(Request $request, int $order): JsonResponse
    {
        return $this->transition($request, $order, 'take', 'Заказ взят в сборку');
    }

    public function release(Request $request, int $order): JsonResponse
    {
        return $this->transition($request, $order, 'release', 'Заказ возвращён в очередь');
    }

    public function complete(Request $request, int $order): JsonResponse
    {
        return $this->transition($request, $order, 'complete', 'Сборка завершена');
    }

    public function escalate(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $item = $this->fulfillmentService->escalate(
                $request->user(),
                $order,
                $validated['comment']
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ для сборки не найден'], 404);
        } catch (DomainException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'message' => 'Заказ передан менеджеру',
            'data' => new PickerOrderResource($item),
        ]);
    }

    public function reportShortage(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'shortage_quantity' => ['required', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->fulfillmentService->reportShortage(
                $request->user(),
                $order,
                (int) $validated['product_id'],
                (int) $validated['shortage_quantity'],
                (string) ($validated['comment'] ?? '')
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ или остаток не найден'], 404);
        } catch (DomainException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'message' => 'Недостача передана менеджеру',
            'data' => new PickerOrderResource($result['order']),
            'fulfillment_issue' => new FulfillmentIssueResource($result['issue']),
        ]);
    }

    private function transition(
        Request $request,
        int $order,
        string $action,
        string $message
    ): JsonResponse {
        try {
            $item = $this->fulfillmentService->{$action}(
                $request->user(),
                $order
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ для сборки не найден'], 404);
        } catch (DomainException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'message' => $message,
            'data' => new PickerOrderResource($item),
        ]);
    }

    private function forbidden(AuthorizationException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'picker_access_denied',
        ], 403);
    }

    private function conflict(DomainException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'picking_transition_rejected',
        ], 409);
    }
}
