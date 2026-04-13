<?php

namespace App\Services;

use Grpc\ChannelCredentials;
use User\V1\GetUserRequest;
use User\V1\UserServiceClient;

class UserGrpcService
{
    private UserServiceClient $client;

    public function __construct()
    {
        $this->client = new UserServiceClient(
            config('services.grpc.user_service_host', env('GRPC_USER_SERVICE_HOST', 'grpc-user-service:50051')),
            [
                'credentials' => ChannelCredentials::createInsecure(),
            ]
        );
    }

    public function getUser(int $id): array
    {
        $request = new GetUserRequest();
        $request->setId($id);

        [$response, $status] = $this->client->GetUser($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            return [
                'ok' => false,
                'error' => $status->details ?: 'gRPC request failed',
                'code' => $status->code,
            ];
        }

        $user = $response->getUser();

        if ($user === null) {
            return [
                'ok' => false,
                'error' => 'User payload is empty',
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'status' => $user->getStatus(),
            ],
        ];
    }
}