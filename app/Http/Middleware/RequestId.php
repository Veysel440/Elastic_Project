<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestId {
    public function handle(Request $r, Closure $next) {
        $id = $r->headers->get('X-Request-Id') ?: Str::uuid()->toString();
        $r->headers->set('X-Request-Id', $id);
        $resp = $next($r);
        $resp->headers->set('X-Request-Id', $id);
        return $resp;
    }
}
