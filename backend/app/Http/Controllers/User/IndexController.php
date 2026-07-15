<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class IndexController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $users = User::with(['addresses', 'roles', 'activeWarehouses'])->get();

        return response()->json([
            'data' => UserResource::collection($users)
        ]);
    }
}
