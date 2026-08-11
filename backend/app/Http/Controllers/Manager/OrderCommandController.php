<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\ManagerOrderResource;
use App\Services\ManagerOrderCommandService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCommandController extends ManagerController
{
    public function __construct(
        protected ManagerOrderCommandService $commandService
    ) {
    }

    public function internalNote(Request $request, int $order): JsonResponse
    {
        $request->merge([
            'comment' => trim((string) $request->input('comment')),
        ]);
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        return $this->execute(
            $request,
            fn () => $this->commandService->addInternalNote(
                $request->user(),
                $order,
                $validated['comment']
            ),
            'Внутренняя заметка добавлена'
        );
    }

    public function confirm(Request $request, int $order): JsonResponse
    {
        return $this->execute(
            $request,
            fn () => $this->commandService->confirm($request->user(), $order),
            'Заказ подтверждён'
        );
    }

    public function cancel(Request $request, int $order): JsonResponse
    {
        $request->merge([
            'reason' => trim((string) $request->input('reason')),
        ]);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->execute(
            $request,
            fn () => $this->commandService->cancel(
                $request->user(),
                $order,
                $validated['reason']
            ),
            'Заказ отменён'
        );
    }

    private function execute(
        Request $request,
        callable $command,
        string $message
    ): JsonResponse {
        try {
            $order = $command();
            $request->attributes->set(
                'manager_active_warehouse_ids',
                $this->commandService->activeWarehouseIds($request->user())
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'manager_order_transition_rejected',
            ], 409);
        }

        return response()->json([
            'message' => $message,
            'data' => new ManagerOrderResource($order),
        ]);
    }
}
