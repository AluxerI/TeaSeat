<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\SellerSyncRequest;
use App\Http\Requests\Seller\UpsertSellerOrderRequest;
use App\Http\Resources\SellerOrderResource;
use App\Services\SellerAccessService;
use App\Services\SellerOrderService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncController extends SellerController
{
    public function __construct(
        SellerAccessService $accessService,
        protected SellerOrderService $orderService
    ) {
        parent::__construct($accessService);
    }

    public function __invoke(SellerSyncRequest $request): JsonResponse
    {
        try {
            $seller = $this->seller($request);
            $device = $this->device($request, $seller);
        } catch (DomainException $exception) {
            return $this->error($exception);
        }

        $results = [];
        foreach ($request->validated('events') as $event) {
            try {
                $results[] = $this->processEvent($seller, $device, $event);
            } catch (ValidationException $exception) {
                $results[] = [
                    'event_id' => $event['event_id'],
                    'result' => 'rejected',
                    'message' => 'Событие не прошло валидацию',
                    'errors' => $exception->errors(),
                ];
            } catch (DomainException $exception) {
                $results[] = [
                    'event_id' => $event['event_id'],
                    'result' => 'rejected',
                    'message' => $exception->getMessage(),
                ];
            } catch (ModelNotFoundException) {
                $results[] = [
                    'event_id' => $event['event_id'],
                    'result' => 'rejected',
                    'message' => 'Заказ продавца не найден',
                ];
            }
        }

        $this->accessService->markSynced($device);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'results' => $results,
        ]);
    }

    private function processEvent($seller, $device, array $event): array
    {
        $action = $event['action'];
        if ($action === 'upsert') {
            $payload = Validator::make(
                $event,
                UpsertSellerOrderRequest::payloadRules()
            )->validate();
            $result = $this->orderService->upsert($seller, $device, $payload);

            return [
                'event_id' => $event['event_id'],
                'result' => $result['duplicate'] ? 'duplicate' : 'accepted',
                'order' => (new SellerOrderResource($result['order']))->resolve(),
            ];
        }

        $command = Validator::make($event, [
            'order_id' => ['required', 'integer'],
            'revision' => ['required', 'integer', 'min:1'],
        ])->validate();

        if ($action === 'cancel') {
            $order = $this->orderService->cancel(
                $seller,
                $device,
                (int) $command['order_id'],
                (int) $command['revision']
            );

            return [
                'event_id' => $event['event_id'],
                'result' => 'cancelled',
                'order' => (new SellerOrderResource($order))->resolve(),
            ];
        }

        $result = $action === 'complete'
            ? $this->orderService->complete(
                $seller,
                $device,
                (int) $command['order_id'],
                (int) $command['revision']
            )
            : $this->orderService->escalate(
                $seller,
                $device,
                (int) $command['order_id'],
                (int) $command['revision']
            );

        return [
            'event_id' => $event['event_id'],
            'result' => $result['result'],
            'conflicts' => $result['conflicts'],
            'order' => (new SellerOrderResource($result['order']))->resolve(),
        ];
    }
}
