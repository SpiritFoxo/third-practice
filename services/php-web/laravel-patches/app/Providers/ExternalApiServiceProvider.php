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
        $service = new AstronomyApiService(
            env('ASTRO_APP_ID'),
            env('ASTRO_APP_SECRET')
        );

        return new CachedAstronomyClient($service);
    });
}
}