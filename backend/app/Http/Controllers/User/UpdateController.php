<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Requests\User\UpdateUserRequest;

class UpdateController extends Controller
{
    public function __invoke(UpdateUserRequest $request, User $user)
    {
        // Обновляем основные данные пользователя
        $user->update($request->validated());
        
        return response()->json([
            'message' => 'Данные пользователя обновлены',
            'user' => new UserResource($user->load('roles'))
        ]);
    }
}
