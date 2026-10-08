<?php

// CORS for frontend (SPA) som kaller API-et og OIDC-token-endepunktet fra egne domener. Bearer-token, ingen cookies.
return [
    'paths' => ['api/*', 'oidc/token', 'oidc/userinfo', '.well-known/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://portal.aprop.no')))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
