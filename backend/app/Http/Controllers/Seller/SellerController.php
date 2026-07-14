<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\SellerAccessService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class SellerController extends Controller
{
    public function __construct(protected SellerAccessService $accessService)
    {
    }

    protected function seller(Request $request): User
    {
        /** @var User $seller */
        $seller = $request->user();
        $this->accessService->assertSeller($seller);

        return $seller;
    }

    protected function device(
        Request $request,
        User $seller,
        ?int $warehouseId = null
    ): StaffDevice {
        $deviceUuid = (string) $request->header('X-Device-UUID');
        if (!Str::isUuid($deviceUuid)) {
            throw new DomainException('Передайте UUID устройства в заголовке X-Device-UUID');
        }

        return $this->accessService->resolveDevice(
            $seller,
            $deviceUuid,
            $warehouseId
        );
    }

    protected function error(DomainException $exception, int $status = 422): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => 'seller_operation_rejected',
        ], $status);
    }
}
