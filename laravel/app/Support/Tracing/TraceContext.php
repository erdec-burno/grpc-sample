<?php

namespace App\Support\Tracing;

class TraceContext
{
    public static function traceparent(): string
    {
        // very simplified W3C traceparent: version-traceid-spanid-flags
        $traceId = bin2hex(random_bytes(16));
        $spanId = bin2hex(random_bytes(8));
        return sprintf('00-%s-%s-01', $traceId, $spanId);
    }
}
