<?php

use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\Traceparent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CorrelationId::class);
        $middleware->append(Traceparent::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
