<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | The frontend is served from its own origin, so the browser will not let it
    | read an API response unless the API says who is allowed to.
    |
    | Origins come from CORS_ALLOWED_ORIGINS in .env, comma separated, e.g.
    |
    |   CORS_ALLOWED_ORIGINS=http://localhost:3000,https://installer.example.com
    |
    | With none set, the common local dev servers are allowed. Never use "*"
    | together with supports_credentials -- browsers reject that pairing, and it
    | would let any site read a logged-in response.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', implode(',', [
            'http://localhost:3000',    // next / react dev server
            'http://localhost:8001',
            'http://127.0.0.1:8001',
            'http://localhost:8000',
            'http://127.0.0.1:8000',
            'http://localhost:8003',
            'http://127.0.0.1:8003',
            'http://127.0.0.1:3000'
        ])))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Read by the frontend when it needs pagination or rate-limit headers.
    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | The public endpoints are token-free, so cookies are not needed. Turn this
    | on only when the frontend starts sending the cart cookie or a session, and
    | then allowed_origins must list exact origins rather than "*".
    */
    'supports_credentials' => false,

];
