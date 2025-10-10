<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogoutFromAllDevicesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();
        
        // Удаляем все токены, кроме текущего
        $user->tokens()
            ->where('id', '!=', $currentToken->id)
            ->delete();
        
        return response()->json([
            'message' => 'Вы вышли со всех устройств, кроме текущего.'
        ]);
    }
}
