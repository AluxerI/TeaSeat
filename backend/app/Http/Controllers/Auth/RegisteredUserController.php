<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Propaganistas\LaravelPhone\Rules\Phone;


class RegisteredUserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'unique:users,phone', 'regex:/^\+7\d{10}$/', new Phone(),'phone:RU'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->string('password')),
        ]);
        $user->assignRole('user');
        $token = $user->createToken('auth-token')->plainTextToken;


        
        event(new Registered($user));

        Auth::login($user);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token
        ], 201); // 201 Created - стандартный код для успешного создания ресурса
        
    }
}
