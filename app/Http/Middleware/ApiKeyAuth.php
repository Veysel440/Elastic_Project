<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiKeyAuth
{
    public function handle(Request $r, Closure $next): Response
    {
        $valid = collect(explode(',', (string) env('API_KEYS', '')))
            ->map('trim')->filter()->all();

        if (!empty($valid)) {
            $key = $r->header('X-API-Key');
            if (!$key || !in_array($key, $valid, true)) {
                return response()->json(['error' => 'unauthorized'], 401);
            }
        }
        return $next($r);
    }
}
