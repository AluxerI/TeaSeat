<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\ManagerOrderRequestResource;
use App\Models\OrderRequest;
use App\Services\OrderRequestService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderRequestController extends ManagerController
{
    public function __construct(protected OrderRequestService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                OrderRequest::STATUS_WAITING,
                OrderRequest::STATUS_IN_REVIEW,
                OrderRequest::STATUS_RESOLVED,
                OrderRequest::STATUS_REJECTED,
                OrderRequest::STATUS_WITHDRAWN,
                'all',
            ])],
            'type' => ['nullable', Rule::in(OrderRequest::TYPES)],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $items = $this->service->managerRequests($request->user(), $filters);
            $summary = $this->service->statusCounts($request->user());
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        }

        return response()->json([
            'data' => ManagerOrderRequestResource::collection($items->items()),
            'summary' => $summary,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, int $orderRequest): JsonResponse
    {
        try {
            $item = $this->service->managerShow($request->user(), $orderRequest);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Обращение не найдено'], 404);
        }

        return response()->json(['data' => new ManagerOrderRequestResource($item)]);
    }

    public function take(Request $request, int $orderRequest): JsonResponse
    {
        return $this->transition($request, $orderRequest, 'take', 'Обращение взято в работу');
    }

    public function release(Request $request, int $orderRequest): JsonResponse
    {
        return $this->transition($request, $orderRequest, 'release', 'Обращение возвращено в очередь');
    }

    public function resolve(Request $request, int $orderRequest): JsonResponse
    {
        $validated = $request->validate([
            'manager_comment' => ['required', 'string', 'max:5000'],
        ]);

        return $this->transition(
            $request,
            $orderRequest,
            'resolve',
            'Обращение завершено',
            $validated['manager_comment']
        );
    }

    public function reject(Request $request, int $orderRequest): JsonResponse
    {
        $validated = $request->validate([
            'manager_comment' => ['required', 'string', 'max:5000'],
        ]);

        return $this->transition(
            $request,
            $orderRequest,
            'reject',
            'Обращение отклонено',
            $validated['manager_comment']
        );
    }

    private function transition(
        Request $request,
        int $requestId,
        string $action,
        string $message,
        ?string $comment = null
    ): JsonResponse {
        try {
            $item = $comment === null
                ? $this->service->{$action}($request->user(), $requestId)
                : $this->service->{$action}($request->user(), $requestId, $comment);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Обращение не найдено'], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'order_request_transition_rejected',
            ], 409);
        }

        return response()->json([
            'message' => $message,
            'data' => new ManagerOrderRequestResource($item),
        ]);
    }
}
