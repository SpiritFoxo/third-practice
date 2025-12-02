<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface AstronomyClientInterface
{
    /**
     * @return Collection<int, \App\DTO\AstroEventData>
     */
    public function getEvents(float $lat, float $lon, int $days): Collection;
}