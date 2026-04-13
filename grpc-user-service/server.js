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

        return handler(span);
      } finally {
        console.log(
          JSON.stringify({
            level: 'info',
            message: 'grpc request completed',
            span: spanName,
            traceparent,
            correlation_id: correlationId,
          })
        );
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
        const user = users.get(call.request.id);
        if (!user) {
          span.setAttribute('rpc.grpc.status_code', grpc.status.NOT_FOUND);
          span.setStatus({ code: SpanStatusCode.ERROR, message: 'User not found' });
          return callback({ code: grpc.status.NOT_FOUND, message: 'User not found' });
        }

        span.setAttribute('rpc.grpc.status_code', grpc.status.OK);
        span.setStatus({ code: SpanStatusCode.OK });
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
        const user = {
          id: idCounter++,
          name: call.request.name,
          email: call.request.email,
          status: 1,
        };

        users.set(user.id, user);
        span.setAttribute('user.id', user.id);
        span.setAttribute('rpc.grpc.status_code', grpc.status.OK);
        span.setStatus({ code: SpanStatusCode.OK });
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
