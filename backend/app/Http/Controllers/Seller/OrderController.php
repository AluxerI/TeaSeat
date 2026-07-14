<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\CompleteSellerOrdersRequest;
use App\Http\Requests\Seller\UpsertSellerOrderRequest;
use App\Http\Resources\SellerOrderResource;
use App\Services\SellerAccessService;
use App\Services\SellerOrderService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends SellerController
{
    public function __construct(
        SellerAccessService $accessService,
        protected SellerOrderService $orderService
    ) {
        parent::__construct($accessService);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,seller_review,manager_review,completed,cancelled'],
            'warehouse_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $seller = $this->seller($request);
            $this->device($request, $seller);
            if (!empty($filters['warehouse_id'])) {
                $this->accessService->assertAssignedWarehouse(
                    $seller,
                    (int) $filters['warehouse_id']
                );
            }
            $orders = $this->orderService->orders($seller, $filters);
        } catch (DomainException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'data' => SellerOrderResource::collection($orders->items()),
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
            $seller = $this->seller($request);
            $this->device($request, $seller);
            $sellerOrder = $this->orderService->findOwn($seller, $order);
        } catch (DomainException $exception) {
            return $this->error($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ продавца не найден'], 404);
        }

        return response()->json(['data' => new SellerOrderResource($sellerOrder)]);
    }

    public function store(UpsertSellerOrderRequest $request): JsonResponse
    {
        try {
            $seller = $this->seller($request);
            $device = $this->device(
                $request,
                $seller,
                (int) $request->validated('warehouse_id')
            );
            $result = $this->orderService->upsert($seller, $device, $request->validated());
        } catch (DomainException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'message' => $result['duplicate']
                ? 'Эта версия заказа уже синхронизирована'
                : 'Заказ продавца синхронизирован',
            'duplicate' => $result['duplicate'],
            'data' => new SellerOrderResource($result['order']),
        ], $result['duplicate'] ? 200 : 201);
    }

    public function update(
        UpsertSellerOrderRequest $request,
        int $order
    ): JsonResponse {
        try {
            $seller = $this->seller($request);
            $existing = $this->orderService->findOwn($seller, $order);
            if (mb_strtolower((string) $existing->client_order_id)
                !== mb_strtolower((string) $request->validated('client_order_id'))) {
                throw new DomainException('UUID в теле не относится к заказу из маршрута');
            }
            $device = $this->device(
                $request,
                $seller,
                (int) $request->validated('warehouse_id')
            );
            $result = $this->orderService->upsert($seller, $device, $request->validated());
        } catch (DomainException $exception) {
            return $this->error($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ продавца не найден'], 404);
        }

        return response()->json([
            'message' => $result['duplicate']
                ? 'Эта версия заказа уже синхронизирована'
                : 'Заказ продавца обновлён',
            'duplicate' => $result['duplicate'],
            'data' => new SellerOrderResource($result['order']),
        ]);
    }

    public function cancel(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $seller = $this->seller($request);
            $device = $this->device($request, $seller);
            $sellerOrder = $this->orderService->cancel(
                $seller,
                $device,
                $order,
                (int) $validated['revision']
            );
        } catch (DomainException $exception) {
            return $this->error($exception, 409);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ продавца не найден'], 404);
        }

        return response()->json([
            'message' => 'Заказ продавца отменён',
            'data' => new SellerOrderResource($sellerOrder),
        ]);
    }

    public function complete(CompleteSellerOrdersRequest $request): JsonResponse
    {
        try {
            $seller = $this->seller($request);
            $device = $this->device($request, $seller);
        } catch (DomainException $exception) {
            return $this->error($exception);
        }

        $results = [];
        foreach ($request->validated('orders') as $command) {
            try {
                $result = $this->orderService->complete(
                    $seller,
                    $device,
                    (int) $command['order_id'],
                    (int) $command['revision']
                );
                $results[] = $this->commandResult($command['order_id'], $result);
            } catch (DomainException $exception) {
                $results[] = [
                    'order_id' => (int) $command['order_id'],
                    'result' => 'rejected',
                    'message' => $exception->getMessage(),
                ];
            } catch (ModelNotFoundException) {
                $results[] = [
                    'order_id' => (int) $command['order_id'],
                    'result' => 'rejected',
                    'message' => 'Заказ продавца не найден',
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function escalate(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $seller = $this->seller($request);
            $device = $this->device($request, $seller);
            $result = $this->orderService->escalate(
                $seller,
                $device,
                $order,
                (int) $validated['revision']
            );
        } catch (DomainException $exception) {
            return $this->error($exception, 409);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ продавца не найден'], 404);
        }

        return response()->json($this->commandResult($order, $result));
    }

    private function commandResult(int $orderId, array $result): array
    {
        return [
            'order_id' => $orderId,
            'result' => $result['result'],
            'conflicts' => $result['conflicts'],
            'order' => (new SellerOrderResource($result['order']))->resolve(),
        ];
    }
}
