<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global
        $middleware->use([
            TrustProxies::class,
            HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // API grubu
        $middleware->group('api', [
            SubstituteBindings::class,
        ]);

        // Alias
        $middleware->alias([
            'throttle' => ThrottleRequests::class,
            'apikey'   => \App\Http\Middleware\ApiKeyAuth::class,   // istersen rotadan kaldır
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
