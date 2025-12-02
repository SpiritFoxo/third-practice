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
        $authString = base64_encode("{$this->appId}:{$this->secret}");

        try {
            $response = Http::withBasicAuth($this->appId, $this->secret)
                ->timeout(10)
                ->retry(2, 100)
                ->withUserAgent('Chrome/142.0.0.0')
                ->withOptions(['verify' => false])
                ->get('https://api.astronomyapi.com/api/v2/bodies/events/Sun', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'from_date' => $from,
                    'to_date' => $to,
                    'time' => date('H:i:s'),
                    'elevation' => 0 
                ]);
            if ($response->failed()) {
                throw new ExternalServiceException("AstronomyAPI error: " . $response->status() . " Body: " . $response->body());
            }

            $rows = $response->json('data.rows', []);
            
            return collect($rows)->flatMap(function ($row) {
                $bodyName = $row['body']['name'] ?? 'Unknown Object';
                $events = $row['events'] ?? [];

                return collect($events)->map(function ($event) use ($bodyName) {
                    
                    $date = $event['eventHighlights']['peak']['date'] 
                        ?? $event['eventHighlights']['partialStart']['date']
                        ?? $event['rise'] 
                        ?? $event['set'] 
                        ?? now()->toIso8601String();

                    $typeName = ucfirst(str_replace('_', ' ', $event['type'] ?? 'event'));

                    return new AstroEventData(
                        name: "{$bodyName}: {$typeName}",
                        date: $date,
                        description: "Event type: {$typeName}. Body: {$bodyName}",
                        raw: $event
                    );
                });
            })->values();

        } catch (ConnectionException $e) {
            throw new ExternalServiceException("AstronomyAPI unavailable", 503, $e);
        }
    }
}