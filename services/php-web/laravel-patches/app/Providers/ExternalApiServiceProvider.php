<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\AstronomyClientInterface;
use App\Services\Astronomy\AstronomyApiService;
use App\Services\Astronomy\CachedAstronomyClient;

class ExternalApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AstronomyClientInterface::class, function ($app) {
            $config = $app['config']['services.astro_api'];
            
            $service = new AstronomyApiService(
                $config['app_id'] ?? '',
                $config['secret'] ?? ''
            );

            return new CachedAstronomyClient($service);
        });
    }
}