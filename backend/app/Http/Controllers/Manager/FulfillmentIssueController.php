<?php

namespace App\Http\Controllers\Manager;

use App\Http\Resources\AffectedOnlineOrderResource;
use App\Http\Resources\ManagerFulfillmentIssueResource;
use App\Services\FulfillmentIssueService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FulfillmentIssueController extends ManagerController
{
    public function __construct(protected FulfillmentIssueService $issueService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:waiting,in_review,closed,all'],
            'warehouse_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'in:online_reservation_conflict,physical_stock_discrepancy'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $manager = $request->user();
            $issues = $this->issueService->issues($manager, $filters);
            $summary = $this->issueService->statusCounts($manager);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        }

        return response()->json([
            'data' => ManagerFulfillmentIssueResource::collection($issues->items()),
            'summary' => $summary,
            'meta' => [
                'current_page' => $issues->currentPage(),
                'last_page' => $issues->lastPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
            ],
        ]);
    }

    public function show(Request $request, int $issue): JsonResponse
    {
        try {
            $item = $this->issueService->findAccessible($request->user(), $issue);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Дело не найдено'], 404);
        }

        return response()->json([
            'data' => new ManagerFulfillmentIssueResource($item),
        ]);
    }

    public function affectedOrders(Request $request, int $issue): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $result = $this->issueService->affectedOrders(
                $request->user(),
                $issue,
                (int) ($validated['per_page'] ?? 20)
            );
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Дело не найдено'], 404);
        }

        $orders = $result['orders'];

        return response()->json([
            'issue' => new ManagerFulfillmentIssueResource($result['issue']),
            'data' => AffectedOnlineOrderResource::collection($orders->items()),
            'summary' => [
                'candidate_orders_count' => $orders->total(),
                'reserved_quantity_total' => $result['reserved_quantity_total'],
                'shortage_quantity' => (int) $result['issue']->shortage_quantity,
            ],
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function take(Request $request, int $issue): JsonResponse
    {
        return $this->transition($request, $issue, 'take', 'Дело взято в работу');
    }

    public function release(Request $request, int $issue): JsonResponse
    {
        return $this->transition($request, $issue, 'release', 'Дело возвращено в очередь');
    }

    public function close(Request $request, int $issue): JsonResponse
    {
        return $this->transition($request, $issue, 'close', 'Дело закрыто');
    }

    private function transition(
        Request $request,
        int $issue,
        string $action,
        string $message
    ): JsonResponse {
        try {
            $item = $this->issueService->{$action}($request->user(), $issue);
        } catch (AuthorizationException $exception) {
            return $this->forbidden($exception);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Дело не найдено'], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'fulfillment_issue_transition_rejected',
            ], 409);
        }

        return response()->json([
            'message' => $message,
            'data' => new ManagerFulfillmentIssueResource($item),
        ]);
    }
}
