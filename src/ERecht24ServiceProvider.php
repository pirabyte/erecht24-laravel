<?php

namespace Pirabyte\ERecht24Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use Pirabyte\ERecht24Laravel\Contracts\LegalTextClient;

class ERecht24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/erecht24.php', 'erecht24');

        $this->app->bind(LegalTextClient::class, SdkLegalTextClient::class);

        $this->app->singleton('erecht24', fn ($app): ERecht24 => new ERecht24(
            $app->make(LegalTextClient::class),
            $app->make(ConfigRepository::class),
            $app->make(CacheFactory::class),
        ));

        $this->app->alias('erecht24', ERecht24::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/erecht24.php' => config_path('erecht24.php'),
            ], 'erecht24-config');
        }
    }
}
