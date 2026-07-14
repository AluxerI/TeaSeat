<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\Seller\RegisterStaffDeviceRequest;
use App\Http\Resources\StaffDeviceResource;
use App\Services\SellerAccessService;
use DomainException;
use Illuminate\Http\JsonResponse;

class DeviceController extends SellerController
{
    public function __construct(SellerAccessService $accessService)
    {
        parent::__construct($accessService);
    }

    public function store(RegisterStaffDeviceRequest $request): JsonResponse
    {
        try {
            $seller = $this->seller($request);
            $token = $seller->currentAccessToken();
            $device = $this->accessService->registerDevice(
                $seller,
                $request->validated('device_uuid'),
                $request->validated('name'),
                $request->validated('warehouse_id'),
                $token && method_exists($token, 'getKey') ? $token->getKey() : null
            );
        } catch (DomainException $exception) {
            return $this->error($exception);
        }

        return response()->json([
            'message' => 'Устройство продавца зарегистрировано',
            'data' => new StaffDeviceResource($device),
        ], 201);
    }
}
