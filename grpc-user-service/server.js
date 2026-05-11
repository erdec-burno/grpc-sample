const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');
const { context, propagation, trace, SpanStatusCode } = require('@opentelemetry/api');
const { createUser, getUserById, initSchema, closePool } = require('./db');
const { startTracing, stopTracing } = require('./tracing');

const packageDef = protoLoader.loadSync('../contracts/user/v1/user.proto');
const grpcObj = grpc.loadPackageDefinition(packageDef);
const userPackage = grpcObj.user.v1;
const port = Number(process.env.PORT || 50051);

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

function grpcError(code, message) {
  return { code, message };
}

async function withRpcSpan(call, spanName, attributes, handler) {
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

  return context.with(parentContext, async () => {
    const span = tracer.startSpan(spanName, { attributes: spanAttributes });
    const overallStart = performance.now();

    return context.with(trace.setSpan(parentContext, span), async () => {
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

        const result = await handler(span);

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
      async (span) => {
        const queryStart = performance.now();

        try {
          const user = await getUserById(call.request.id);
          const queryMs = elapsedMs(queryStart);

          if (!user) {
            span.setAttribute('latency.db_query_ms', queryMs);
            span.setAttribute('db.operation', 'SELECT');
            span.setAttribute('db.collection.name', 'grpc_users');
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
                  db_query: queryMs,
                },
              })
            );
            return callback(grpcError(grpc.status.NOT_FOUND, 'User not found'));
          }

          span.setAttribute('latency.db_query_ms', queryMs);
          span.setAttribute('db.operation', 'SELECT');
          span.setAttribute('db.collection.name', 'grpc_users');
          span.setAttribute('user.id', user.id);
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
                db_query: queryMs,
              },
            })
          );
          callback(null, {
            user: {
              id: Number(user.id),
              name: user.name,
              email: user.email,
              status: Number(user.status),
            },
          });
        } catch (error) {
          const queryMs = elapsedMs(queryStart);
          span.recordException(error);
          span.setAttribute('latency.db_query_ms', queryMs);
          span.setAttribute('db.operation', 'SELECT');
          span.setAttribute('db.collection.name', 'grpc_users');
          span.setAttribute('rpc.grpc.status_code', grpc.status.INTERNAL);
          span.setStatus({ code: SpanStatusCode.ERROR, message: error.message });
          console.log(
            JSON.stringify({
              level: 'error',
              message: 'grpc database failure',
              span: 'UserService/GetUser',
              traceparent: firstMetadataValue(call, 'traceparent'),
              correlation_id: firstMetadataValue(call, 'x-correlation-id'),
              grpc_status_code: grpc.status.INTERNAL,
              error: error.message,
              latency_breakdown_ms: {
                db_query: queryMs,
              },
            })
          );
          callback(grpcError(grpc.status.INTERNAL, 'Database query failed'));
        }
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
      async (span) => {
        const buildStart = performance.now();
        const newUser = {
          name: call.request.name,
          email: call.request.email,
          status: 1,
        };
        const buildMs = elapsedMs(buildStart);

        const insertStart = performance.now();

        try {
          const user = await createUser(newUser);
          const insertMs = elapsedMs(insertStart);

          span.setAttribute('latency.user_build_ms', buildMs);
          span.setAttribute('latency.db_insert_ms', insertMs);
          span.setAttribute('db.operation', 'INSERT');
          span.setAttribute('db.collection.name', 'grpc_users');
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
                db_insert: insertMs,
              },
            })
          );
          callback(null, {
            user: {
              id: Number(user.id),
              name: user.name,
              email: user.email,
              status: Number(user.status),
            },
          });
        } catch (error) {
          const insertMs = elapsedMs(insertStart);
          const statusCode = error.code === '23505' ? grpc.status.ALREADY_EXISTS : grpc.status.INTERNAL;
          const message = error.code === '23505' ? 'User with this email already exists' : 'Database insert failed';

          span.recordException(error);
          span.setAttribute('latency.user_build_ms', buildMs);
          span.setAttribute('latency.db_insert_ms', insertMs);
          span.setAttribute('db.operation', 'INSERT');
          span.setAttribute('db.collection.name', 'grpc_users');
          span.setAttribute('rpc.grpc.status_code', statusCode);
          span.setStatus({ code: SpanStatusCode.ERROR, message: error.message });
          console.log(
            JSON.stringify({
              level: error.code === '23505' ? 'warning' : 'error',
              message: 'grpc database failure',
              span: 'UserService/CreateUser',
              traceparent: firstMetadataValue(call, 'traceparent'),
              correlation_id: firstMetadataValue(call, 'x-correlation-id'),
              grpc_status_code: statusCode,
              error: error.message,
              latency_breakdown_ms: {
                user_build: buildMs,
                db_insert: insertMs,
              },
            })
          );
          callback(grpcError(statusCode, message));
        }
      }
    );
  },
});

async function main() {
  await startTracing();
  await initSchema();

  server.bindAsync(`0.0.0.0:${port}`, grpc.ServerCredentials.createInsecure(), (error) => {
    if (error) {
      throw error;
    }

    console.log(`gRPC server running on port ${port}`);
  });
}

process.on('SIGTERM', async () => {
  server.tryShutdown(() => {
    closePool()
      .catch((error) => console.warn('Failed to close Postgres pool', error))
      .finally(() => {
        stopTracing().finally(() => process.exit(0));
      });
  });
});

process.on('SIGINT', async () => {
  server.tryShutdown(() => {
    closePool()
      .catch((error) => console.warn('Failed to close Postgres pool', error))
      .finally(() => {
        stopTracing().finally(() => process.exit(0));
      });
  });
});

main().catch(async (error) => {
  console.error('Failed to start gRPC service with tracing', error);
  await closePool().catch(() => {});
  await stopTracing();
  process.exit(1);
});
