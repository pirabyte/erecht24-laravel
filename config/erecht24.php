<?php

return [
    'api_key' => env('ERECHT24_API_KEY'),
    'plugin_key' => env('ERECHT24_PLUGIN_KEY'),
    'use_demo_plugin_key' => env('ERECHT24_USE_DEMO_PLUGIN_KEY', true),
    'demo_plugin_key' => env('ERECHT24_DEMO_PLUGIN_KEY', '3jh4uhn8u69i97kj9timk466748996ikhkjhlk67plli08lhkijgh8z4363gr53v'),
    'language' => env('ERECHT24_LANGUAGE', 'de'),

    'cache' => [
        'enabled' => env('ERECHT24_CACHE_ENABLED', true),
        'store' => env('ERECHT24_CACHE_STORE'),
        'ttl' => env('ERECHT24_CACHE_TTL', 3600),
        'prefix' => env('ERECHT24_CACHE_PREFIX', 'erecht24'),
    ],
];
