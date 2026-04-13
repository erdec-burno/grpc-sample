const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');
const { trace, SpanStatusCode } = require('@opentelemetry/api');
const { startTracing, stopTracing } = require('./tracing');

const packageDef = protoLoader.loadSync('../contracts/user/v1/user.proto');
const grpcObj = grpc.loadPackageDefinition(packageDef);
const userPackage = grpcObj.user.v1;
const port = Number(process.env.PORT || 50051);

const users = new Map();
let idCounter = 1;

const server = new grpc.Server();
const tracer = trace.getTracer('grpc-user-service');

server.addService(userPackage.UserService.service, {
  GetUser: (call, callback) => {
    const span = tracer.startSpan('UserService/GetUser', {
      attributes: {
        'rpc.system': 'grpc',
        'rpc.service': 'user.v1.UserService',
        'rpc.method': 'GetUser',
        'user.id': call.request.id,
      },
    });

    try {
      const user = users.get(call.request.id);
      if (!user) {
        span.setAttribute('rpc.grpc.status_code', grpc.status.NOT_FOUND);
        span.setStatus({ code: SpanStatusCode.ERROR, message: 'User not found' });
        return callback({ code: grpc.status.NOT_FOUND, message: 'User not found' });
      }

      span.setAttribute('rpc.grpc.status_code', grpc.status.OK);
      span.setStatus({ code: SpanStatusCode.OK });
      callback(null, { user });
    } finally {
      span.end();
    }
  },

  CreateUser: (call, callback) => {
    const span = tracer.startSpan('UserService/CreateUser', {
      attributes: {
        'rpc.system': 'grpc',
        'rpc.service': 'user.v1.UserService',
        'rpc.method': 'CreateUser',
        'user.name.length': call.request.name.length,
      },
    });

    try {
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
    } finally {
      span.end();
    }
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
