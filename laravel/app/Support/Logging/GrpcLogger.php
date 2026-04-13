<?php

namespace App\Support\Logging;

use Illuminate\Support\Facades\Log;

class GrpcLogger
{
    public static function log(string $method, array $meta = []): void
    {
        Log::info('grpc_call', $meta + ['method' => $method]);
    }
}
