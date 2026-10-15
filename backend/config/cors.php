<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Comma-separated list of frontend origins, e.g. https://autopass.ge,https://app.autopass.ge
    // '*' is dropped: with credentials only explicitly listed origins may read responses.
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
        fn ($origin) => $origin !== '' && $origin !== '*'
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Accept-Language', 'X-Api-Key'],

    'exposed_headers' => [],

    'max_age' => 600,

    // Needed for the httpOnly refresh cookie (fetch credentials: 'include').
    'supports_credentials' => true,
];
