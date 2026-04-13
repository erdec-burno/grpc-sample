const { NodeSDK } = require('@opentelemetry/sdk-node');
const { OTLPTraceExporter } = require('@opentelemetry/exporter-trace-otlp-http');
const { Resource } = require('@opentelemetry/resources');
const { SemanticResourceAttributes } = require('@opentelemetry/semantic-conventions');

const serviceName = process.env.OTEL_SERVICE_NAME || 'grpc-user-service';
const otlpBaseEndpoint = process.env.OTEL_EXPORTER_OTLP_ENDPOINT;
const tracesEndpoint = process.env.OTEL_EXPORTER_OTLP_TRACES_ENDPOINT
  || (otlpBaseEndpoint ? `${otlpBaseEndpoint.replace(/\/$/, '')}/v1/traces` : null);

const tracingEnabled = process.env.OTEL_TRACING_ENABLED !== 'false' && Boolean(tracesEndpoint);

const sdk = tracingEnabled
  ? new NodeSDK({
      traceExporter: new OTLPTraceExporter({
        url: tracesEndpoint,
      }),
      resource: new Resource({
        [SemanticResourceAttributes.SERVICE_NAME]: serviceName,
      }),
    })
  : null;

async function startTracing() {
  if (!tracingEnabled) {
    console.log(`OpenTelemetry tracing disabled for ${serviceName}`);
    return;
  }

  try {
    await sdk.start();
    console.log(`OpenTelemetry tracing enabled: ${serviceName} -> ${tracesEndpoint}`);
  } catch (error) {
    console.warn('OpenTelemetry tracing startup failed, continuing without tracing', error);
  }
}

async function stopTracing() {
  if (!sdk) {
    return;
  }

  try {
    await sdk.shutdown();
  } catch (error) {
    console.warn('OpenTelemetry tracing shutdown failed', error);
  }
}

module.exports = {
  startTracing,
  stopTracing,
};
