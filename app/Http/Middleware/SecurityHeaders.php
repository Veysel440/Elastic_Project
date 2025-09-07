<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class SecurityHeaders {
    public function handle(Request $r, Closure $next) {
        $resp = $next($r);
        $h = $resp->headers;
        $h->set('X-Content-Type-Options','nosniff');
        $h->set('X-Frame-Options','DENY');
        $h->set('Referrer-Policy','no-referrer');
        $h->set('Permissions-Policy','geolocation=(), microphone=()');
        return $resp;
    }
}
