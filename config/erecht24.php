<?php

return [
    'api_key' => env('ERECHT24_API_KEY'),
    'plugin_key' => env('ERECHT24_PLUGIN_KEY') ?: 'vRuG4GQHxYb9MkxU3HURJTyDUHyDyE3scTV4vzzR8VPHbwyT3krWzM6vS4vmeqfm',
    'language' => env('ERECHT24_LANGUAGE', 'de'),

    'cache' => [
        'enabled' => env('ERECHT24_CACHE_ENABLED', true),
        'store' => env('ERECHT24_CACHE_STORE'),
        'ttl' => env('ERECHT24_CACHE_TTL', 3600),
        'prefix' => env('ERECHT24_CACHE_PREFIX', 'erecht24'),
    ],
];
