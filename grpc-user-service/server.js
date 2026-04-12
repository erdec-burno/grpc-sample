const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');

const packageDef = protoLoader.loadSync('../contracts/user/v1/user.proto');
const grpcObj = grpc.loadPackageDefinition(packageDef);
const userPackage = grpcObj.user.v1;

const users = new Map();
let idCounter = 1;

const server = new grpc.Server();

server.addService(userPackage.UserService.service, {
  GetUser: (call, callback) => {
    const user = users.get(call.request.id);
    if (!user) {
      return callback({ code: grpc.status.NOT_FOUND, message: 'User not found' });
    }
    callback(null, { user });
  },

  CreateUser: (call, callback) => {
    const user = {
      id: idCounter++,
      name: call.request.name,
      email: call.request.email,
      status: 1,
    };

    users.set(user.id, user);
    callback(null, { user });
  },
});

server.bindAsync('0.0.0.0:50051', grpc.ServerCredentials.createInsecure(), () => {
  console.log('gRPC server running on port 50051');
  server.start();
});
