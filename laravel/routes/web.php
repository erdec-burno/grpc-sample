<?php

use App\Services\UserGrpcService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthz', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'laravel-app',
    ]);
});

Route::get('/readyz', function () {
    $grpcHost = (string) config('services.grpc.user_service_host');
    [$grpcAddress, $grpcPort] = array_pad(explode(':', $grpcHost, 2), 2, null);
    $grpcPort = (int) ($grpcPort ?: 50051);
    $databaseHealthy = false;

    try {
        DB::select('SELECT 1');
        $databaseHealthy = true;

        $socket = @fsockopen($grpcAddress, $grpcPort, $errorCode, $errorMessage, 1.0);
        if ($socket === false) {
            throw new \RuntimeException($errorMessage ?: 'gRPC service is unreachable', $errorCode);
        }

        fclose($socket);

        return response()->json([
            'status' => 'ready',
            'service' => 'laravel-app',
            'checks' => [
                'database' => 'ok',
                'grpc_user_service' => 'ok',
            ],
        ]);
    } catch (\Throwable $exception) {
        return response()->json([
            'status' => 'not_ready',
            'service' => 'laravel-app',
            'checks' => [
                'database' => $databaseHealthy ? 'ok' : 'failed',
                'grpc_user_service' => 'failed',
            ],
            'error' => $exception->getMessage(),
        ], 503);
    }
});

Route::get('/users/{id}', function (int $id, UserGrpcService $userGrpcService) {
    $result = $userGrpcService->getUser($id);

    if (($result['ok'] ?? false) === false) {
        return response()->json(
            ['error' => $result['error'] ?? 'Unknown error'],
            ($result['code'] ?? null) === \Grpc\STATUS_NOT_FOUND ? 404 : 500
        );
    }

    return response()->json($result['data']);
});

Route::post('/users', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string'],
        'email' => ['required', 'email'],
    ]);

    $result = app(UserGrpcService::class)
        ->createUser($data['name'], $data['email']);

    if (($result['ok'] ?? false) === false) {
        return response()->json(
            ['error' => $result['error'] ?? 'Unknown error'],
            500
        );
    }

    return response()->json($result['data'], 201);
})->withoutMiddleware([ValidateCsrfToken::class]);
