<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;

class ShowController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->load('addresses');
        return new UserResource($user);
    }
}