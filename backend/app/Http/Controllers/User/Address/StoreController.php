<?php

namespace App\Http\Controllers\User\Address;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressClientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
        ]);

        $address = $request->user()->addresses()->create($validated);

        return response()->json([
            'message' => 'Адрес успешно добавлен',
            'address' => new AddressClientResource($address)
        ], 201);
    }
}
