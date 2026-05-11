<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\SpanExporterFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class AppServiceProvider extends ServiceProvider
{
    private static bool $otelBootstrapped = false;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (self::$otelBootstrapped) {
            return;
        }

        if (! env('OTEL_EXPORTER_OTLP_ENDPOINT') && ! env('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT')) {
            return;
        }

        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor((new SpanExporterFactory())->create()),
            null,
            ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => env('OTEL_SERVICE_NAME', 'laravel-app'),
            ]))
        );

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        self::$otelBootstrapped = true;
    }
}
