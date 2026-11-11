<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | La version web (PWA) de l'application appelle l'API depuis une autre origine.
    | L'authentification se fait par jeton porteur (en-tête Authorization), sans cookie :
    | `supports_credentials` reste donc désactivé. En production, listez les origines
    | autorisées dans CORS_ALLOWED_ORIGINS (séparées par des virgules) plutôt que « * ».
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
