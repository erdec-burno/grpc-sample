<?php

namespace App\Support\Tracing;

use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

class Span
{
    public static function run(string $name, callable $fn, array $meta = [])
    {
        $start = microtime(true);
        $context = [
            'span.name' => $name,
            ...$meta,
        ];
        $span = Globals::tracerProvider()
            ->getTracer('grpc-sample.laravel.app')
            ->spanBuilder($name)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes($meta)
            ->startSpan();
        $scope = $span->activate();

        try {
            $result = $fn();
            $duration = round((microtime(true) - $start) * 1000, 3);
            $span->setAttribute('span.duration_ms', $duration);
            $span->setStatus(StatusCode::STATUS_OK);

            Log::info('span.completed', [
                ...$context,
                'span.duration_ms' => $duration,
                'span.status' => 'ok',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $start) * 1000, 3);
            $span->recordException($e);
            $span->setAttribute('span.duration_ms', $duration);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());

            Log::error('span.failed', [
                ...$context,
                'span.duration_ms' => $duration,
                'span.status' => 'error',
                'error.message' => $e->getMessage(),
                'error.type' => $e::class,
            ]);

            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
