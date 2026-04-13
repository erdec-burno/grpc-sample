<?php

use App\Services\UserGrpcService;
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