<?php

namespace App\Services;

use App\Support\Grpc\CircuitBreaker;
use App\Support\Grpc\GrpcExecutor;
use App\Support\Grpc\RetryPolicy;
use Grpc\ChannelCredentials;
use User\V1\CreateUserRequest;
use User\V1\GetUserRequest;
use User\V1\UserServiceClient;

class UserGrpcService
{
    private UserServiceClient $client;
    private GrpcExecutor $executor;

    public function __construct()
    {
        $this->client = new UserServiceClient(
            config('services.grpc.user_service_host'),
            ['credentials' => ChannelCredentials::createInsecure()]
        );

        $this->executor = new GrpcExecutor(
            new RetryPolicy(3, 100),
            new CircuitBreaker(3, 5),
            2000 // timeout ms
        );
    }

    public function getUser(int $id): array
    {
        return $this->executor->execute(function () use ($id) {
            $request = new GetUserRequest();
            $request->setId($id);

            [$response, $status] = $this->client->GetUser($request)->wait();

            if ($status->code !== \Grpc\STATUS_OK) {
                return [
                    'ok' => false,
                    'error' => $status->details,
                    'code' => $status->code,
                ];
            }

            $user = $response->getUser();

            return [
                'ok' => true,
                'data' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'status' => $user->getStatus(),
                ],
            ];
        });
    }

    public function createUser(string $name, string $email): array
    {
        return $this->executor->execute(function () use ($name, $email) {
            $request = new CreateUserRequest();
            $request->setName($name);
            $request->setEmail($email);

            [$response, $status] = $this->client->CreateUser($request)->wait();

            if ($status->code !== \Grpc\STATUS_OK) {
                return [
                    'ok' => false,
                    'error' => $status->details,
                    'code' => $status->code,
                ];
            }

            $user = $response->getUser();

            return [
                'ok' => true,
                'data' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'status' => $user->getStatus(),
                ],
            ];
        });
    }
}