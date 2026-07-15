<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssembledGiftResource;
use App\Models\Product;
use App\Services\GiftAssemblyService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssembledGiftController extends Controller
{
    public function __construct(protected GiftAssemblyService $assemblyService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        try {
            $products = $this->assemblyService->catalog(
                $request->user(),
                isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
                (int) ($data['per_page'] ?? 20)
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json([
            'data' => AssembledGiftResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function replenish(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'idempotency_key' => ['required', 'uuid'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'consumed_items' => ['required', 'array', 'min:1', 'max:100'],
            'consumed_items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'consumed_items.*.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ]);
        try {
            $result = $this->assemblyService->replenish(
                $request->user(),
                $product,
                (int) $data['warehouse_id'],
                (int) $data['quantity'],
                $data['consumed_items'],
                $data['idempotency_key'],
                $data['comment'] ?? null
            );
            return response()->json(['data' => $result]);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'gift_assembly_rejected',
            ], 422);
        }
    }
}
