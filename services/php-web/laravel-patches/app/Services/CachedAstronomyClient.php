<?php

namespace App\Services;

use App\Contracts\AstronomyClientInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CachedAstronomyClient implements AstronomyClientInterface
{
    public function __construct(
        private AstronomyClientInterface $inner,
        private int $ttlSeconds = 3600
    ) {}

    public function getEvents(float $lat, float $lon, int $days): Collection
    {
        $key = "astro_events:{$lat}:{$lon}:{$days}:" . now()->format('Y-m-d');

        return Cache::remember($key, $this->ttlSeconds, function () use ($lat, $lon, $days) {
            return $this->inner->getEvents($lat, $lon, $days);
        });
    }
}