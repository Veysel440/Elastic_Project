<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class EnforceJson {
    public function handle(Request $r, Closure $next) {
        if (in_array($r->method(), ['POST','PUT','PATCH'], true)) {
            $ct = $r->headers->get('Content-Type','');
            if (!str_starts_with(strtolower($ct), 'application/json')) {
                return response()->json(['error'=>'unsupported_media_type'], 415);
            }
        }
        return $next($r);
    }
}
