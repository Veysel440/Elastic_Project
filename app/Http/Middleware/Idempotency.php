<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class Idempotency {
    public function handle(Request $r, Closure $next) {
        if ($r->method() !== 'POST') return $next($r);
        $key = $r->header('Idempotency-Key');
        if (!$key) return $next($r);
        $cacheKey = 'idem:'.sha1($r->path().'|'.$key);
        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached['body'], $cached['status'], $cached['headers']);
        }
        $resp = $next($r);
        Cache::put($cacheKey, [
            'status'=>$resp->getStatusCode(),
            'headers'=>['X-Request-Id'=>$resp->headers->get('X-Request-Id')],
            'body'=>json_decode($resp->getContent(), true),
        ], now()->addMinutes(10));
        return $resp;
    }
}
