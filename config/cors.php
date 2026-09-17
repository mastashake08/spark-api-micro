<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Only /api/* is opened up here — the Inertia/Vue pages this app serves
    | itself are same-origin and don't need CORS. This is what lets a
    | separately-hosted frontend (e.g. the spark-mls-client web examples on
    | a different origin/port during local dev) call this app's Spark proxy
    | routes without the browser blocking the request.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
