<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Zet in productie CORS_ALLOWED_ORIGINS=https://jullie-frontend.nl in .env
    // (meerdere origins scheiden met een komma). Standaard: alles toegestaan.
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // We gebruiken Bearer-tokens, geen cookies.
    'supports_credentials' => false,
];
