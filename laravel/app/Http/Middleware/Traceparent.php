<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;

class Traceparent
{
    public function handle(Request $request, Closure $next)
    {
        $propagator = Globals::propagator();
        $parentContext = $propagator->extract($request->headers->all(), ArrayAccessGetterSetter::getInstance());
        $tracer = Globals::tracerProvider()->getTracer('grpc-sample.laravel.http');
        $span = $tracer->spanBuilder(sprintf('%s %s', $request->method(), '/'.ltrim($request->path(), '/')))
            ->setParent($parentContext)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes([
                'http.request.method' => $request->method(),
                'url.path' => '/'.ltrim($request->path(), '/'),
                'url.query' => (string) $request->getQueryString(),
            ])
            ->startSpan();

        $scope = $span->activate();

        $requestCarrier = [];
        $propagator->inject($requestCarrier, ArrayAccessGetterSetter::getInstance());
        app()->instance('traceparent', $requestCarrier['traceparent'] ?? null);

        try {
            $response = $next($request);

            $span->setAttribute('http.response.status_code', $response->getStatusCode());
            if ($response->getStatusCode() >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'HTTP request failed');
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            $responseCarrier = [];
            $propagator->inject($responseCarrier, ArrayAccessGetterSetter::getInstance());
            foreach (['traceparent', 'tracestate'] as $header) {
                if (isset($responseCarrier[$header])) {
                    $response->headers->set($header, $responseCarrier[$header]);
                }
            }

            return $response;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());

            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
