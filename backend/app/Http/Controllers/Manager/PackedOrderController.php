<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\AdminOrderResource;
use App\Services\OrderFulfillmentService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackedOrderController extends ManagerController
{
    public function __construct(
        protected OrderFulfillmentService $fulfillmentService
    ) {
    }

    public function returnToStock(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $item = $this->fulfillmentService->returnPackedOrderToStock(
                $request->user(),
                $order,
                $validated['reason']
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Упакованный заказ не найден'], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'packed_order_return_rejected',
            ], 409);
        }

        return response()->json([
            'message' => 'Упаковка разобрана, товар возвращён в резерв склада',
            'data' => new AdminOrderResource($item),
        ]);
    }
}
