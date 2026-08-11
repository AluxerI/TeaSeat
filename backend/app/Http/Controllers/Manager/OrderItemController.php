<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\ManagerOrderAdjustmentResource;
use App\Http\Resources\ManagerOrderResource;
use App\Services\ManagerOrderItemService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemController extends ManagerController
{
    public function __construct(
        protected ManagerOrderItemService $itemService
    ) {
    }

    public function changeQuantity(
        Request $request,
        int $order,
        int $item
    ): JsonResponse {
        $validated = $this->validateCommand($request, [
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        return $this->execute(
            $request,
            fn (): array => $this->itemService->changeQuantity(
                $request->user(),
                $order,
                $item,
                (int) $validated['quantity'],
                $validated['operation_id'],
                $validated['reason'],
                $validated['fulfillment_issue_id'] ?? null
            ),
            'Количество товара изменено'
        );
    }

    public function store(Request $request, int $order): JsonResponse
    {
        $validated = $this->validateCommand($request, [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        return $this->execute(
            $request,
            fn (): array => $this->itemService->addProduct(
                $request->user(),
                $order,
                (int) $validated['product_id'],
                (int) $validated['quantity'],
                $validated['operation_id'],
                $validated['reason'],
                $validated['fulfillment_issue_id'] ?? null
            ),
            'Товар добавлен в заказ'
        );
    }

    public function replace(
        Request $request,
        int $order,
        int $item
    ): JsonResponse {
        $validated = $this->validateCommand($request, [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        return $this->execute(
            $request,
            fn (): array => $this->itemService->replaceProduct(
                $request->user(),
                $order,
                $item,
                (int) $validated['product_id'],
                (int) $validated['quantity'],
                $validated['operation_id'],
                $validated['reason'],
                $validated['fulfillment_issue_id'] ?? null
            ),
            'Товар в заказе заменён'
        );
    }

    public function remove(
        Request $request,
        int $order,
        int $item
    ): JsonResponse {
        $validated = $this->validateCommand($request);

        return $this->execute(
            $request,
            fn (): array => $this->itemService->removeProduct(
                $request->user(),
                $order,
                $item,
                $validated['operation_id'],
                $validated['reason'],
                $validated['fulfillment_issue_id'] ?? null
            ),
            'Товар удалён из заказа'
        );
    }

    public function removeGift(
        Request $request,
        int $order,
        int $gift
    ): JsonResponse {
        $validated = $this->validateCommand($request);

        return $this->execute(
            $request,
            fn (): array => $this->itemService->removeGift(
                $request->user(),
                $order,
                $gift,
                $validated['operation_id'],
                $validated['reason'],
                $validated['fulfillment_issue_id'] ?? null
            ),
            'Подарочный набор удалён из заказа'
        );
    }

    public function replaceGift(
        Request $request,
        int $order,
        int $gift
    ): JsonResponse {
        $validated = $this->validateCommand($request, [
            'gift_id' => ['required', 'integer', 'exists:gifts,id'],
            'gift_version' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->execute(
            $request,
            fn (): array => $this->itemService->replaceGift(
                $request->user(),
                $order,
                $gift,
                (int) $validated['gift_id'],
                (int) $validated['gift_version'],
                (int) $validated['quantity'],
                $validated['operation_id'],
                $validated['reason'],
                $validated['fulfillment_issue_id'] ?? null
            ),
            'Подарочный набор в заказе заменён'
        );
    }

    private function validateCommand(Request $request, array $rules = []): array
    {
        $request->merge([
            'reason' => trim((string) $request->input('reason')),
        ]);

        return $request->validate($rules + [
            'operation_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'fulfillment_issue_id' => [
                'nullable',
                'integer',
                'exists:fulfillment_issues,id',
            ],
        ]);
    }

    private function execute(
        Request $request,
        callable $command,
        string $message
    ): JsonResponse {
        try {
            $result = $command();
            $request->attributes->set(
                'manager_active_warehouse_ids',
                $this->itemService->activeWarehouseIds($request->user())
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Заказ, позиция или проблема не найдены',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'manager_order_edit_rejected',
            ], 409);
        }

        return response()->json([
            'message' => $message,
            'already_applied' => $result['already_applied'],
            'adjustment' => new ManagerOrderAdjustmentResource(
                $result['adjustment']
            ),
            'data' => new ManagerOrderResource($result['order']),
        ]);
    }
}
