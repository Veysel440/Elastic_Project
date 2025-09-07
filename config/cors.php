<?php

return [
    'paths' => ['api/*'],
    'allowed_origins' => explode(',', env('CORS_ORIGINS', 'http://localhost:5173')),
    'allowed_origins_patterns' => [],
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 0,
    'supports_credentials' => false,
];
