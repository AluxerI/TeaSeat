<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;

class JsonResponseMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions(
                JSON_UNESCAPED_UNICODE | 
                JSON_UNESCAPED_SLASHES |
                JSON_PRETTY_PRINT // опционально для красивого форматирования
            );
        }
        
        return $response;
    }
}
