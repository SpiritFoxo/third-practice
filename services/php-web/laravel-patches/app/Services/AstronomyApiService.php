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

            $rows = $response->json('data.table.rows', []);

            return collect($rows)
                ->flatMap(function ($row) {
                    $bodyName = $row['entry']['name'] ?? 'Unknown Body';
                    $cells = $row['cells'] ?? [];

                    return collect($cells)->flatMap(function ($cell) use ($bodyName) {
                        $events = $cell['events'] ?? $cell;

                        return collect($events)->map(function ($event) use ($bodyName) {
                            $date = data_get($event, 'eventHighlights.peak.date')
                                ?? data_get($event, 'eventHighlights.partialStart.date')
                                ?? data_get($event, 'rise')
                                ?? data_get($event, 'set')
                                ?? now('UTC')->toIso8601String();

                            $typeName = ucfirst(str_replace('_', ' ', (string)($event['type'] ?? $event['eventType'] ?? 'event')));

                            return new AstroEventData(
                                name: "{$bodyName}: {$typeName}",
                                date: $date,
                                description: "Event type: {$typeName}. Body: {$bodyName}",
                                raw: $event
                            );
                        });
                    });
                })
                ->values();

        } catch (ConnectionException $e) {
            throw new ExternalServiceException("AstronomyAPI unavailable", 503, $e);
        }
    }
}