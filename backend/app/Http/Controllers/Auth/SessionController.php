<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __invoke(Request $request)
    {
        $sessions = $request->user()->tokens()
            ->select('id', 'name', 'last_used_at', 'created_at')
            ->orderBy('last_used_at', 'desc')
            ->get();
        
        return response()->json([
            'sessions' => $sessions
        ]);
    }
}
