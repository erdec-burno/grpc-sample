<?php

namespace App\Services;

use Grpc\ChannelCredentials;
use User\V1\CreateUserRequest;
use User\V1\GetUserRequest;
use User\V1\UserServiceClient;

class TracedUserGrpcService
{
    private UserServiceClient $client;

    public function __construct()
    {
        $this->client = new UserServiceClient(
            config('services.grpc.user_service_host'),
            ['credentials' => ChannelCredentials::createInsecure()]
        );
    }

    private function metadata(): array
    {
        $tp = app()->bound('traceparent') ? app('traceparent') : null;
        return $tp ? ['traceparent' => [$tp]] : [];
    }

    public function getUser(int $id): array
    {
        $request = new GetUserRequest();
        $request->setId($id);

        [$response, $status] = $this->client->GetUser($request, [], $this->metadata())->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            return ['ok' => false, 'error' => $status->details, 'code' => $status->code];
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
    }

    public function createUser(string $name, string $email): array
    {
        $request = new CreateUserRequest();
        $request->setName($name);
        $request->setEmail($email);

        [$response, $status] = $this->client->CreateUser($request, [], $this->metadata())->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            return ['ok' => false, 'error' => $status->details, 'code' => $status->code];
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
    }
}
