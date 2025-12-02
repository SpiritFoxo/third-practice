<?php

namespace App\Services;

use App\Contracts\AstronomyClientInterface;
use App\DTO\AstroEventData;
use App\Exceptions\ExternalServiceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class AstronomyApiService implements AstronomyClientInterface
{
    public function __construct(
        private string $appId,
        private string $secret
    ) {}

    public function getEvents(float $lat, float $lon, int $days): Collection
    {
        $from = now('UTC')->toDateString();
        $to = now('UTC')->addDays($days)->toDateString();

        try {
            $response = Http::withBasicAuth($this->appId, $this->secret)
                ->timeout(5)
                ->retry(2, 100)
                ->withUserAgent('monolith-iss/2.0')
                ->get('https://api.astronomyapi.com/api/v2/bodies/events', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'from' => $from,
                    'to' => $to,
                ]);

            if ($response->failed()) {
                throw new ExternalServiceException("AstronomyAPI error: " . $response->status());
            }

            $data = $response->json('data');
            
            return collect($data)->map(fn($item) => new AstroEventData(
                name: $item['name'] ?? 'Unknown Event',
                date: $item['date'] ?? '',
                description: $item['description'] ?? null,
                raw: $item
            ));

        } catch (ConnectionException $e) {
            throw new ExternalServiceException("AstronomyAPI unavailable", 503, $e);
        }
    }
}