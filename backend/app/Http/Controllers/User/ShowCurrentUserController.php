<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ShowCurrentUserController extends Controller
{
    public function __invoke(Request $request)
    {
        // Загружаем связанные данные профиля
        $user = $request->user()->load('addresses');
        return new UserResource($user);
    }
}
