<?php

namespace App\Services;

use App\Support\Grpc\CircuitBreaker;
use App\Support\Grpc\GrpcExecutor;
use App\Support\Grpc\RetryPolicy;
use App\Support\Tracing\Span;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
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
            2000
        );
    }

    private function contextMeta(array $meta = []): array
    {
        $carrier = [];
        Globals::propagator()->inject($carrier, ArrayAccessGetterSetter::getInstance());

        return [
            'traceparent' => $carrier['traceparent'] ?? (app()->bound('traceparent') ? app('traceparent') : null),
            'correlation_id' => app()->bound('correlation_id') ? app('correlation_id') : null,
            ...$meta,
        ];
    }

    private function metadata(): array
    {
        $carrier = [];
        Globals::propagator()->inject($carrier, ArrayAccessGetterSetter::getInstance());
        $metadata = [];

        if (isset($carrier['traceparent'])) {
            $metadata['traceparent'] = [$carrier['traceparent']];
        }

        if (isset($carrier['tracestate'])) {
            $metadata['tracestate'] = [$carrier['tracestate']];
        }

        if (app()->bound('correlation_id')) {
            $metadata['x-correlation-id'] = [app('correlation_id')];
        }

        return $metadata;
    }

    private function elapsedMs(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 3);
    }

    public function getUser(int $id): array
    {
        return Span::run('laravel.grpc.user_service.get_user.total', function () use ($id) {
            return $this->executor->execute(function () use ($id) {
                $overallStart = microtime(true);

                Log::info('grpc request started', $this->contextMeta([
                    'rpc.service' => 'user.v1.UserService',
                    'rpc.method' => 'GetUser',
                    'user.id' => $id,
                ]));

                $metadataStart = microtime(true);
                $metadata = Span::run('laravel.grpc.user_service.get_user.metadata', fn () => $this->metadata());
                $metadataMs = $this->elapsedMs($metadataStart);

                $request = new GetUserRequest();
                $request->setId($id);

                $grpcCallStart = microtime(true);
                [$response, $status] = Span::run(
                    'laravel.grpc.user_service.get_user.call',
                    fn () => $this->client->GetUser($request, $metadata)->wait(),
                    $this->contextMeta([
                        'rpc.service' => 'user.v1.UserService',
                        'rpc.method' => 'GetUser',
                    ])
                );
                $grpcCallMs = $this->elapsedMs($grpcCallStart);

                if ($status->code !== \Grpc\STATUS_OK) {
                    Log::warning('grpc request failed', $this->contextMeta([
                        'rpc.service' => 'user.v1.UserService',
                        'rpc.method' => 'GetUser',
                        'user.id' => $id,
                        'grpc.status_code' => $status->code,
                        'grpc.error' => $status->details,
                        'latency_breakdown_ms' => [
                            'metadata' => $metadataMs,
                            'grpc_call' => $grpcCallMs,
                            'total' => $this->elapsedMs($overallStart),
                        ],
                    ]));

                    return [
                        'ok' => false,
                        'error' => $status->details,
                        'code' => $status->code,
                    ];
                }

                $mapStart = microtime(true);
                $result = Span::run('laravel.grpc.user_service.get_user.map_response', function () use ($response) {
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
                $mapMs = $this->elapsedMs($mapStart);

                Log::info('grpc request completed', $this->contextMeta([
                    'rpc.service' => 'user.v1.UserService',
                    'rpc.method' => 'GetUser',
                    'user.id' => $result['data']['id'] ?? null,
                    'grpc.status_code' => \Grpc\STATUS_OK,
                    'latency_breakdown_ms' => [
                        'metadata' => $metadataMs,
                        'grpc_call' => $grpcCallMs,
                        'response_map' => $mapMs,
                        'total' => $this->elapsedMs($overallStart),
                    ],
                ]));

                return $result;
            });
        }, $this->contextMeta([
            'rpc.service' => 'user.v1.UserService',
            'rpc.method' => 'GetUser',
            'user.id' => $id,
        ]));
    }

    public function createUser(string $name, string $email): array
    {
        return Span::run('laravel.grpc.user_service.create_user.total', function () use ($name, $email) {
            return $this->executor->execute(function () use ($name, $email) {
                $overallStart = microtime(true);

                Log::info('grpc request started', $this->contextMeta([
                    'rpc.service' => 'user.v1.UserService',
                    'rpc.method' => 'CreateUser',
                    'user.email' => $email,
                ]));

                $metadataStart = microtime(true);
                $metadata = Span::run('laravel.grpc.user_service.create_user.metadata', fn () => $this->metadata());
                $metadataMs = $this->elapsedMs($metadataStart);

                $requestBuildStart = microtime(true);
                $request = Span::run('laravel.grpc.user_service.create_user.build_request', function () use ($name, $email) {
                    $request = new CreateUserRequest();
                    $request->setName($name);
                    $request->setEmail($email);

                    return $request;
                });
                $requestBuildMs = $this->elapsedMs($requestBuildStart);

                $grpcCallStart = microtime(true);
                [$response, $status] = Span::run(
                    'laravel.grpc.user_service.create_user.call',
                    fn () => $this->client->CreateUser($request, $metadata)->wait(),
                    $this->contextMeta([
                        'rpc.service' => 'user.v1.UserService',
                        'rpc.method' => 'CreateUser',
                    ])
                );
                $grpcCallMs = $this->elapsedMs($grpcCallStart);

                if ($status->code !== \Grpc\STATUS_OK) {
                    Log::warning('grpc request failed', $this->contextMeta([
                        'rpc.service' => 'user.v1.UserService',
                        'rpc.method' => 'CreateUser',
                        'user.email' => $email,
                        'grpc.status_code' => $status->code,
                        'grpc.error' => $status->details,
                        'latency_breakdown_ms' => [
                            'metadata' => $metadataMs,
                            'request_build' => $requestBuildMs,
                            'grpc_call' => $grpcCallMs,
                            'total' => $this->elapsedMs($overallStart),
                        ],
                    ]));

                    return [
                        'ok' => false,
                        'error' => $status->details,
                        'code' => $status->code,
                    ];
                }

                $mapStart = microtime(true);
                $result = Span::run('laravel.grpc.user_service.create_user.map_response', function () use ($response) {
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
                $mapMs = $this->elapsedMs($mapStart);

                Log::info('grpc request completed', $this->contextMeta([
                    'rpc.service' => 'user.v1.UserService',
                    'rpc.method' => 'CreateUser',
                    'user.id' => $result['data']['id'] ?? null,
                    'user.email' => $result['data']['email'] ?? $email,
                    'grpc.status_code' => \Grpc\STATUS_OK,
                    'latency_breakdown_ms' => [
                        'metadata' => $metadataMs,
                        'request_build' => $requestBuildMs,
                        'grpc_call' => $grpcCallMs,
                        'response_map' => $mapMs,
                        'total' => $this->elapsedMs($overallStart),
                    ],
                ]));

                return $result;
            });
        }, $this->contextMeta([
            'rpc.service' => 'user.v1.UserService',
            'rpc.method' => 'CreateUser',
            'user.email' => $email,
        ]));
    }
}
