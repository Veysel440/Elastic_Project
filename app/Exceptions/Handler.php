<?php

namespace App\Exceptions;

class Handler
{
    public function register(): void {
        $this->renderable(function (\Throwable $e, $req) {
            $rid = $req->headers->get('X-Request-Id');
            return response()->json([
                'error'      => 'validation_error',
                'request_id' => $rid,
                'fields'     => $e->errors(),
            ], 422);
        });
    }

}
