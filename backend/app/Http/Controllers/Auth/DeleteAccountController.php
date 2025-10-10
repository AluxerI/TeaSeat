<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeleteAccountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user) {
        // Обновляем статус аккаунта
        $user->update(['is_active' => false]);
        
        //  выполняем мягкое удаление аккаунта
        $user->delete();
        });

        return response()->json([
            'message' => 'Ваш аккаунт успешно удален.'
        ]);
    }
}