<?php

use Pirabyte\ERecht24Laravel\Contracts\LegalTextClient;
use Pirabyte\ERecht24Laravel\SdkLegalTextClient;
use Pirabyte\ERecht24Laravel\Tests\TestCase;

uses(TestCase::class);

it('merges the default config', function () {
    expect(config('erecht24'))
        ->toBeArray()
        ->toHaveKey('api_key')
        ->toHaveKey('plugin_key')
        ->toHaveKey('use_demo_plugin_key')
        ->toHaveKey('demo_plugin_key')
        ->and(config('erecht24.language'))->toBe('de')
        ->and(config('erecht24.use_demo_plugin_key'))->toBeTrue()
        ->and(config('erecht24.cache.enabled'))->toBeTrue()
        ->and(config('erecht24.cache.ttl'))->toBe(3600)
        ->and(config('erecht24.cache.prefix'))->toBe('erecht24');
});

it('publishes config under the erecht24 config tag', function () {
    $publishedConfig = config_path('erecht24.php');

    if (file_exists($publishedConfig)) {
        unlink($publishedConfig);
    }

    $this->artisan('vendor:publish', [
        '--tag' => 'erecht24-config',
        '--force' => true,
    ])->assertExitCode(0);

    expect($publishedConfig)
        ->toBeFile()
        ->and(require $publishedConfig)->toHaveKey('api_key');

    unlink($publishedConfig);
});

it('binds the SDK legal text client', function () {
    expect($this->app->make(LegalTextClient::class))
        ->toBeInstanceOf(SdkLegalTextClient::class);
});
