<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class LogRequest {
    public function handle(Request $r, Closure $next) {
        $t0 = microtime(true);
        $resp = $next($r);
        $ms = (int) ((microtime(true) - $t0) * 1000);
        Log::info('http', [
            'rid'    => $r->headers->get('X-Request-Id'),
            'm'      => $r->getMethod(),
            'p'      => $r->path(),
            'q'      => $r->query(),
            'status' => $resp->getStatusCode(),
            'ms'     => $ms,
            'ip'     => $r->ip(),
        ]);
        return $resp;
    }
}
