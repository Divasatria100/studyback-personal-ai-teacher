<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The SPA is normally served through the Nginx reverse proxy (same origin,
    | /api is proxied to the backend), so CORS is not needed for the primary
    | flow. Direct-frontend-to-backend dev setups (e.g. Vite on :5173 calling
    | the backend on :8000) are supported through this env-driven config.
    |
    */

    'paths' => ['api/*', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost,http://localhost:5173,http://localhost:3000,http://127.0.0.1:5173'
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
