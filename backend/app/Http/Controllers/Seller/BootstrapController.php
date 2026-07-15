<?php

namespace App\Http\Controllers\Seller;

use App\Http\Resources\StaffDeviceResource;
use App\Models\Product;
use App\Services\SellerAccessService;
use App\Services\SellerPriceSnapshotService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends SellerController
{
    public function __construct(
        SellerAccessService $accessService,
        protected SellerPriceSnapshotService $priceSnapshotService
    ) {
        parent::__construct($accessService);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
        ]);

        try {
            $seller = $this->seller($request);
            $warehouse = $this->accessService->assertAssignedWarehouse(
                $seller,
                (int) $validated['warehouse_id']
            );
            $device = $this->device($request, $seller, $warehouse->id);
            $products = Product::query()
                ->whereHas('inventories', fn ($query) =>
                    $query->where('warehouse_id', $warehouse->id))
                ->with([
                    'inventories' => fn ($query) =>
                        $query->where('warehouse_id', $warehouse->id),
                    'sub_subcategories.subcategory.category',
                ])
                ->orderBy('id')
                ->get()
                ->map(function (Product $product) use ($seller): array {
                    $inventory = $product->inventories->first();
                    $priceSnapshot = $this->priceSnapshotService->snapshot($product, $seller);

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'stock_unit' => $product->stockUnit(),
                        'sale_step' => $product->saleStep(),
                        'price_unit_quantity' => $product->priceUnitQuantity(),
                        'pricing' => $priceSnapshot['pricing'],
                        'pricing_token' => $priceSnapshot['pricing_token'],
                        'stock' => [
                            'quantity' => (int) $inventory->quantity,
                            'reserved_online_quantity' => (int) $inventory->reserved_online_quantity,
                            'reserved_seller_quantity' => (int) $inventory->reserved_seller_quantity,
                            'available_quantity' => $inventory->availableQuantity(),
                            'shortage_quantity' => $inventory->shortageQuantity(),
                        ],
                    ];
                })
                ->values();
            $this->accessService->markSynced($device, $warehouse->id);
        } catch (DomainException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'data' => [
                'server_time' => now()->toIso8601String(),
                'price_snapshot_ttl_hours' => (int) config('seller.price_snapshot_ttl_hours', 48),
                'warehouse' => [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'city' => $warehouse->city,
                    'type' => $warehouse->type,
                ],
                'device' => new StaffDeviceResource($device->fresh()),
                'products' => $products,
            ],
        ]);
    }
}
