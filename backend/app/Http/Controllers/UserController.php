<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;

class UserController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');
        return new UserResource($user);
    }
}