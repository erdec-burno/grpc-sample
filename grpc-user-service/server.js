const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');
const { context, propagation, trace, SpanStatusCode } = require('@opentelemetry/api');
const { startTracing, stopTracing } = require('./tracing');

const packageDef = protoLoader.loadSync('../contracts/user/v1/user.proto');
const grpcObj = grpc.loadPackageDefinition(packageDef);
const userPackage = grpcObj.user.v1;
const port = Number(process.env.PORT || 50051);

const users = new Map();
let idCounter = 1;

const server = new grpc.Server();
const tracer = trace.getTracer('grpc-user-service');
const metadataGetter = {
  keys(carrier) {
    return Object.keys(carrier);
  },
  get(carrier, key) {
    return carrier[key];
  },
};

function metadataCarrier(call) {
  const carrier = {};

  for (const [key, value] of Object.entries(call.metadata.getMap())) {
    carrier[key] = Array.isArray(value) ? value : [String(value)];
  }

  const traceparent = call.metadata.get('traceparent');
  if (traceparent.length > 0) {
    carrier.traceparent = traceparent.map(String);
  }

  const tracestate = call.metadata.get('tracestate');
  if (tracestate.length > 0) {
    carrier.tracestate = tracestate.map(String);
  }

  return carrier;
}

function firstMetadataValue(call, key) {
  const values = call.metadata.get(key);
  return values.length > 0 ? String(values[0]) : null;
}

function elapsedMs(start) {
  return Number(((performance.now() - start)).toFixed(3));
}

function withRpcSpan(call, spanName, attributes, handler) {
  const parentContext = propagation.extract(context.active(), metadataCarrier(call), metadataGetter);
  const traceparent = firstMetadataValue(call, 'traceparent');
  const correlationId = firstMetadataValue(call, 'x-correlation-id');
  const spanAttributes = { ...attributes };

  if (traceparent) {
    spanAttributes.traceparent = traceparent;
  }

  if (correlationId) {
    spanAttributes['correlation.id'] = correlationId;
  }

  return context.with(parentContext, () => {
    const span = tracer.startSpan(spanName, { attributes: spanAttributes });
    const overallStart = performance.now();

    return context.with(trace.setSpan(parentContext, span), () => {
      try {
        console.log(
          JSON.stringify({
            level: 'info',
            message: 'grpc request started',
            span: spanName,
            traceparent,
            correlation_id: correlationId,
            attributes,
          })
        );

        const result = handler(span);

        console.log(
          JSON.stringify({
            level: 'info',
            message: 'grpc request completed',
            span: spanName,
            traceparent,
            correlation_id: correlationId,
            latency_breakdown_ms: {
              total: elapsedMs(overallStart),
            },
          })
        );

        return result;
      } catch (error) {
        console.log(
          JSON.stringify({
            level: 'error',
            message: 'grpc request failed',
            span: spanName,
            traceparent,
            correlation_id: correlationId,
            error: error.message,
            latency_breakdown_ms: {
              total: elapsedMs(overallStart),
            },
          })
        );

        throw error;
      } finally {
        span.end();
      }
    });
  });
}

server.addService(userPackage.UserService.service, {
  GetUser: (call, callback) => {
    return withRpcSpan(
      call,
      'UserService/GetUser',
      {
        'rpc.system': 'grpc',
        'rpc.service': 'user.v1.UserService',
        'rpc.method': 'GetUser',
        'user.id': call.request.id,
      },
      (span) => {
        const lookupStart = performance.now();
        const user = users.get(call.request.id);
        const lookupMs = elapsedMs(lookupStart);

        if (!user) {
          span.setAttribute('latency.lookup_ms', lookupMs);
          span.setAttribute('rpc.grpc.status_code', grpc.status.NOT_FOUND);
          span.setStatus({ code: SpanStatusCode.ERROR, message: 'User not found' });
          console.log(
            JSON.stringify({
              level: 'warning',
              message: 'grpc business failure',
              span: 'UserService/GetUser',
              traceparent: firstMetadataValue(call, 'traceparent'),
              correlation_id: firstMetadataValue(call, 'x-correlation-id'),
              grpc_status_code: grpc.status.NOT_FOUND,
              latency_breakdown_ms: {
                user_lookup: lookupMs,
              },
            })
          );
          return callback({ code: grpc.status.NOT_FOUND, message: 'User not found' });
        }

        span.setAttribute('latency.lookup_ms', lookupMs);
        span.setAttribute('rpc.grpc.status_code', grpc.status.OK);
        span.setStatus({ code: SpanStatusCode.OK });
        console.log(
          JSON.stringify({
            level: 'info',
            message: 'grpc business success',
            span: 'UserService/GetUser',
            traceparent: firstMetadataValue(call, 'traceparent'),
            correlation_id: firstMetadataValue(call, 'x-correlation-id'),
            grpc_status_code: grpc.status.OK,
            user_id: user.id,
            latency_breakdown_ms: {
              user_lookup: lookupMs,
            },
          })
        );
        callback(null, { user });
      }
    );
  },

  CreateUser: (call, callback) => {
    return withRpcSpan(
      call,
      'UserService/CreateUser',
      {
        'rpc.system': 'grpc',
        'rpc.service': 'user.v1.UserService',
        'rpc.method': 'CreateUser',
        'user.name.length': call.request.name.length,
      },
      (span) => {
        const buildStart = performance.now();
        const user = {
          id: idCounter++,
          name: call.request.name,
          email: call.request.email,
          status: 1,
        };
        const buildMs = elapsedMs(buildStart);

        const storeStart = performance.now();
        users.set(user.id, user);
        const storeMs = elapsedMs(storeStart);

        span.setAttribute('latency.user_build_ms', buildMs);
        span.setAttribute('latency.user_store_ms', storeMs);
        span.setAttribute('user.id', user.id);
        span.setAttribute('rpc.grpc.status_code', grpc.status.OK);
        span.setStatus({ code: SpanStatusCode.OK });
        console.log(
          JSON.stringify({
            level: 'info',
            message: 'grpc business success',
            span: 'UserService/CreateUser',
            traceparent: firstMetadataValue(call, 'traceparent'),
            correlation_id: firstMetadataValue(call, 'x-correlation-id'),
            grpc_status_code: grpc.status.OK,
            user_id: user.id,
            latency_breakdown_ms: {
              user_build: buildMs,
              user_store: storeMs,
            },
          })
        );
        callback(null, { user });
      }
    );
  },
});

async function main() {
  await startTracing();

  server.bindAsync(`0.0.0.0:${port}`, grpc.ServerCredentials.createInsecure(), (error) => {
    if (error) {
      throw error;
    }

    console.log(`gRPC server running on port ${port}`);
  });
}

process.on('SIGTERM', async () => {
  server.tryShutdown(() => {
    stopTracing().finally(() => process.exit(0));
  });
});

process.on('SIGINT', async () => {
  server.tryShutdown(() => {
    stopTracing().finally(() => process.exit(0));
  });
});

main().catch(async (error) => {
  console.error('Failed to start gRPC service with tracing', error);
  await stopTracing();
  process.exit(1);
});
