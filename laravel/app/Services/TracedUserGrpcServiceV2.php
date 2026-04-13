<?php

namespace App\Services;

use App\Support\Tracing\Span;
use Grpc\ChannelCredentials;
use User\V1\CreateUserRequest;
use User\V1\GetUserRequest;
use User\V1\UserServiceClient;

class TracedUserGrpcServiceV2
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
        return ['traceparent' => [app('traceparent')]];
    }

    public function getUser(int $id): array
    {
        return Span::run('grpc.getUser', function () use ($id) {
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
        });
    }

    public function createUser(string $name, string $email): array
    {
        return Span::run('grpc.createUser', function () use ($name, $email) {
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
        });
    }
}
