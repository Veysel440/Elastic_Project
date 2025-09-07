<?php

return[

    'paths' => ['api/*'],
    'allowed_origins' => explode(',', env('CORS_ORIGINS','http://localhost:5173')),
    'allowed_methods' => ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
    'allowed_headers' => ['Content-Type','X-API-Key','X-Request-Id','Idempotency-Key'],
    'exposed_headers' => ['X-Request-Id'],

];
