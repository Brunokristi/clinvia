<?php

$configuredOrigins = array_values(array_filter(array_map(
    static fn ($origin) => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

$devOrigins = [
    'http://localhost:5174',
    'http://127.0.0.1:5174',
];

return [
    'paths' => [
        'public/*',
        'public/companies/*',
    ],

    'allowed_methods' => [
        'GET',
        'OPTIONS',
    ],

    'allowed_origins' => array_values(array_unique([
        ...$devOrigins,
        ...$configuredOrigins,
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'X-API-Key',
        'Accept',
        'Content-Type',
        'Origin',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];