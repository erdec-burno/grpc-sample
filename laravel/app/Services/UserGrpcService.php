<?php

namespace App\Services;

use App\Support\Grpc\CircuitBreaker;
use App\Support\Grpc\GrpcExecutor;
use App\Support\Grpc\RetryPolicy;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;
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

    private function metadata(): array
    {
        $metadata = [];

        if (app()->bound('traceparent')) {
            $metadata['traceparent'] = [app('traceparent')];
        }

        if (app()->bound('correlation_id')) {
            $metadata['x-correlation-id'] = [app('correlation_id')];
        }

        return $metadata;
    }

    public function getUser(int $id): array
    {
        return $this->executor->execute(function () use ($id) {
            Log::info('grpc request started', [
                'rpc.service' => 'user.v1.UserService',
                'rpc.method' => 'GetUser',
                'user.id' => $id,
                'traceparent' => app()->bound('traceparent') ? app('traceparent') : null,
                'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
            ]);

            $request = new GetUserRequest();
            $request->setId($id);

            [$response, $status] = $this->client->GetUser($request, $this->metadata())->wait();

            if ($status->code !== \Grpc\STATUS_OK) {
                Log::warning('grpc request failed', [
                    'rpc.service' => 'user.v1.UserService',
                    'rpc.method' => 'GetUser',
                    'user.id' => $id,
                    'grpc.status_code' => $status->code,
                    'grpc.error' => $status->details,
                    'traceparent' => app()->bound('traceparent') ? app('traceparent') : null,
                    'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
                ]);

                return [
                    'ok' => false,
                    'error' => $status->details,
                    'code' => $status->code,
                ];
            }

            $user = $response->getUser();

            Log::info('grpc request completed', [
                'rpc.service' => 'user.v1.UserService',
                'rpc.method' => 'GetUser',
                'user.id' => $user?->getId(),
                'grpc.status_code' => \Grpc\STATUS_OK,
                'traceparent' => app()->bound('traceparent') ? app('traceparent') : null,
                'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
            ]);

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
            Log::info('grpc request started', [
                'rpc.service' => 'user.v1.UserService',
                'rpc.method' => 'CreateUser',
                'user.email' => $email,
                'traceparent' => app()->bound('traceparent') ? app('traceparent') : null,
                'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
            ]);

            $request = new CreateUserRequest();
            $request->setName($name);
            $request->setEmail($email);

            [$response, $status] = $this->client->CreateUser($request, $this->metadata())->wait();

            if ($status->code !== \Grpc\STATUS_OK) {
                Log::warning('grpc request failed', [
                    'rpc.service' => 'user.v1.UserService',
                    'rpc.method' => 'CreateUser',
                    'user.email' => $email,
                    'grpc.status_code' => $status->code,
                    'grpc.error' => $status->details,
                    'traceparent' => app()->bound('traceparent') ? app('traceparent') : null,
                    'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
                ]);

                return [
                    'ok' => false,
                    'error' => $status->details,
                    'code' => $status->code,
                ];
            }

            $user = $response->getUser();

            Log::info('grpc request completed', [
                'rpc.service' => 'user.v1.UserService',
                'rpc.method' => 'CreateUser',
                'user.id' => $user?->getId(),
                'user.email' => $user?->getEmail(),
                'grpc.status_code' => \Grpc\STATUS_OK,
                'traceparent' => app()->bound('traceparent') ? app('traceparent') : null,
                'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
            ]);

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
