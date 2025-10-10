<?php

namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DeleteUserController extends Controller
{
    public function __invoke(User $user): JsonResponse
    {
        $user->delete();
        return response()->json([
            'message' => 'Пользователь успешно удален (мягкое удаление).'
        ]);
    }
}
