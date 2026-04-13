<?php

namespace App\Support\Tracing;

use Illuminate\Support\Facades\Log;

class Span
{
    public static function run(string $name, callable $fn, array $meta = [])
    {
        $start = microtime(true);
        $context = [
            'span.name' => $name,
            ...$meta,
        ];

        try {
            $result = $fn();
            $duration = round((microtime(true) - $start) * 1000, 3);

            Log::info('span.completed', [
                ...$context,
                'span.duration_ms' => $duration,
                'span.status' => 'ok',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 3);

            Log::error('span.failed', [
                ...$context,
                'span.duration_ms' => $duration,
                'span.status' => 'error',
                'error.message' => $e->getMessage(),
                'error.type' => $e::class,
            ]);

            throw $e;
        }
    }
}
