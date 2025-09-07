<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class MaxBodySize {
    public function handle(Request $r, Closure $next) {
        $len = (int) $r->headers->get('Content-Length', 0);
        $max = (int) config('api.max_body_bytes', 1_000_000);
        if ($len > 0 && $len > $max) return response()->json(['error'=>'payload_too_large'], 413);
        return $next($r);
    }
}
