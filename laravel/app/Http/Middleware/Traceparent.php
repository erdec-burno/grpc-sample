<?php

namespace App\Http\Middleware;

use App\Support\Tracing\TraceContext;
use Closure;
use Illuminate\Http\Request;

class Traceparent
{
    public function handle(Request $request, Closure $next)
    {
        $tp = $request->headers->get('traceparent') ?: TraceContext::traceparent();
        app()->instance('traceparent', $tp);

        $response = $next($request);
        return $response->header('traceparent', $tp);
    }
}
