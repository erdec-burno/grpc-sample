<?php

namespace App\Support\Tracing;

use Illuminate\Support\Facades\Log;

class Span
{
    public static function run(string $name, callable $fn, array $meta = [])
    {
        $start = microtime(true);

        try {
            $result = $fn();
            $duration = (microtime(true) - $start) * 1000;

            Log::info('span', [
                'name' => $name,
                'duration_ms' => $duration,
                ...$meta,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;

            Log::error('span_error', [
                'name' => $name,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                ...$meta,
            ]);

            throw $e;
        }
    }
}
