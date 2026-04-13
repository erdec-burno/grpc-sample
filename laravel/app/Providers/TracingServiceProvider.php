<?php

namespace App\Providers;

use App\Services\TracedUserGrpcService;
use Illuminate\Support\ServiceProvider;

class TracingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Services\UserGrpcService::class, function () {
            return new TracedUserGrpcService();
        });
    }
}
