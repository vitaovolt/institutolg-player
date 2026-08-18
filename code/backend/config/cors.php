<?php

$frontend = env('FRONTEND_URL');
$local = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

$origins = array_values(array_filter(array_unique([
    $frontend,
    ...(env('APP_ENV', 'production') === 'local' ? $local : []),
])));

if (env('APP_ENV') === 'production' && empty($frontend)) {
    $origins = [];
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'Idempotency-Key'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
