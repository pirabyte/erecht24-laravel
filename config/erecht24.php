<?php

return [
    'api_key' => env('ERECHT24_API_KEY'),
    'plugin_key' => env('ERECHT24_PLUGIN_KEY'),
    'language' => env('ERECHT24_LANGUAGE', 'de'),

    'cache' => [
        'enabled' => env('ERECHT24_CACHE_ENABLED', true),
        'store' => env('ERECHT24_CACHE_STORE'),
        'ttl' => env('ERECHT24_CACHE_TTL', 3600),
        'prefix' => env('ERECHT24_CACHE_PREFIX', 'erecht24'),
    ],
];
