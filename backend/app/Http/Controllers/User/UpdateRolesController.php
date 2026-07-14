<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Services\StaffAccessService;

class UpdateRolesController extends Controller
{
     public function __invoke(Request $request, User $user)
    {
        // Проверяем право суперпользователя
        $this->authorize('manage users'); // Убедитесь, что такое право существует

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        // Синхронизируем роли пользователя
        $user->syncRoles($request->roles);

        if (!$user->isStaff()) {
            app(StaffAccessService::class)->syncActiveLocations($user, []);
        } else {
            $user->clearCache();
        }

        // Обновляем данные пользователя
        $user->load(['roles', 'activeWarehouses']);

        return response()->json([
            'message' => 'Роли пользователя успешно обновлены.',
            'user' => new UserResource($user) // Используйте ваш UserResource
        ]);
    }
}
