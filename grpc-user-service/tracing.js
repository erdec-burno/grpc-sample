const { NodeSDK } = require('@opentelemetry/sdk-node');
const { OTLPTraceExporter } = require('@opentelemetry/exporter-trace-otlp-http');
const { resourceFromAttributes } = require('@opentelemetry/resources');
const { SemanticResourceAttributes } = require('@opentelemetry/semantic-conventions');

const serviceName = process.env.OTEL_SERVICE_NAME || 'grpc-user-service';
const otlpEndpoint = process.env.OTEL_EXPORTER_OTLP_ENDPOINT || 'http://jaeger:4318';
const tracesEndpoint =
  process.env.OTEL_EXPORTER_OTLP_TRACES_ENDPOINT ||
  `${otlpEndpoint.replace(/\/$/, '')}/v1/traces`;

const traceExporter = new OTLPTraceExporter({
  url: tracesEndpoint,
});

const sdk = new NodeSDK({
  traceExporter,
  resource: resourceFromAttributes({
    [SemanticResourceAttributes.SERVICE_NAME]: serviceName,
  }),
});

async function startTracing() {
  await sdk.start();
  console.log(`OpenTelemetry tracing enabled: ${serviceName} -> ${tracesEndpoint}`);
}

async function stopTracing() {
  await sdk.shutdown();
}

module.exports = {
  startTracing,
  stopTracing,
};
