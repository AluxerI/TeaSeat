<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;

class ShowController extends Controller
{
    public function __invoke(Request $request, User $user)
    {
        $user->load(['addresses', 'roles', 'activeWarehouses']);

        return new UserResource($user);
    }
}
