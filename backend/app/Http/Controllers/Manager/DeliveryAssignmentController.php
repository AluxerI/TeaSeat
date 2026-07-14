<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\CourierDeliveryResource;
use App\Models\User;
use App\Services\DeliveryWorkflowService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryAssignmentController extends ManagerController
{
    public function __construct(
        protected DeliveryWorkflowService $deliveryService
    ) {
    }

    public function assign(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'courier_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $delivery = $this->deliveryService->assignByManager(
                $request->user(),
                $order,
                User::query()->findOrFail((int) $validated['courier_id'])
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Доставка или курьер не найдены',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'delivery_assignment_rejected',
            ], 409);
        }

        return response()->json([
            'message' => 'Курьер назначен менеджером',
            'data' => new CourierDeliveryResource($delivery),
        ]);
    }
}
