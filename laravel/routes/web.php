<?php

use App\Services\UserGrpcService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
