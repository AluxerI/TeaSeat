<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class UseRedisCache
{
    public function handle($request, Closure $next)
    {
        // Принудительно используем Redis
        config(['cache.default' => 'redis']);
        config(['permission.cache.store' => 'redis']);
        
        return $next($request);
    }
}