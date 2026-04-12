<?php

use Illuminate\Support\Facades\Route;
use App\Services\UserGrpcService;

Route::get('/users/{id}', function ($id) {
    $service = app(UserGrpcService::class);
    return response()->json($service->getUser((int)$id));
});
