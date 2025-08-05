<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redis;

class ClearRedisCache
{
    public function handle($request, Closure $next)
    {
        if ($request->is('admin/*')) {
            Redis::flushdb();
        }
        
        return $next($request);
    }
}