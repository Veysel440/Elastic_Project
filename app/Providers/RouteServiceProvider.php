<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void {
        RateLimiter::for('api', function (Request $r) {
            $id = $r->header('X-API-Key') ?: $r->ip();
            return [ Limit::perMinute(120)->by($id) ];
        });
        RateLimiter::for('ops', fn(Request $r) =>
        [ Limit::perMinute(20)->by($r->header('X-API-Key') ?: $r->ip()) ]
        );
    }
}
