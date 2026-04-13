<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CorrelationId
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->headers->get('X-Correlation-ID') ?: (string) Str::uuid();
        app()->instance('correlation_id', $id);
        $response = $next($request);
        return $response->header('X-Correlation-ID', $id);
    }
}
