<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Wildcard '*' was fine for early local dev but is too permissive for a
    // token-authenticated API — any website could call it from a browser.
    // Set CORS_ALLOWED_ORIGINS in .env (comma-separated) for each real environment;
    // these two localhost origins are just Vite's default dev server addresses.
    'allowed_origins' => array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000')
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
