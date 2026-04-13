<?php

use App\Http\Controllers\UserController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/users/{id}', [UserController::class, 'show']);

Route::post('/users', [UserController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class]);
