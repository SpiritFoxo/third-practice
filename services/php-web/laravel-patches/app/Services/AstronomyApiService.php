<?php
namespace App\Services;

use App\Contracts\AstronomyClientInterface;
use App\DTO\AstroEventData;
use App\Exceptions\ExternalServiceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

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
        
        // Use a fixed time or format it properly
        $time = now('UTC')->format('H:i:s');

        try {
            $response = Http::withBasicAuth($this->appId, $this->secret)
                ->timeout(10)
                ->retry(2, 100)
                ->withHeaders([
                    'User-Agent' => 'Chrome/142.0.0.0'
                ])
                ->get('https://api.astronomyapi.com/api/v2/bodies/events/Sun', [
                    'latitude' => (string)$lat,
                    'longitude' => (string)$lon,
                    'from_date' => $from,
                    'to_date' => $to,
                    'elevation' => 0,
                    'time' => $time
                ]);

            // Log the response for debugging
            Log::info('AstronomyAPI Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $response->effectiveUri()
            ]);

            if ($response->failed()) {
                throw new ExternalServiceException(
                    "AstronomyAPI error: " . $response->status() . " Body: " . $response->body()
                );
            }

            $data = $response->json();
            
            // Check if we have the expected structure
            if (!isset($data['data']['table']['rows'])) {
                Log::warning('Unexpected API response structure', ['data' => $data]);
                return collect([]);
            }

            $rows = $data['data']['table']['rows'];

            return collect($rows)
                ->flatMap(function ($row) {
                    $bodyName = $row['entry']['name'] ?? 'Unknown Body';
                    $cells = $row['cells'] ?? [];
                    
                    return collect($cells)->map(function ($cell) use ($bodyName) {
                        $type = $cell['type'] ?? 'unknown';
                        
                        // Handle different event types
                        $date = data_get($cell, 'eventHighlights.peak.date')
                            ?? data_get($cell, 'eventHighlights.partialStart.date')
                            ?? data_get($cell, 'rise')
                            ?? data_get($cell, 'set')
                            ?? now('UTC')->toIso8601String();
                        
                        $typeName = ucfirst(str_replace('_', ' ', $type));
                        
                        return new AstroEventData(
                            name: "{$bodyName}: {$typeName}",
                            date: $date,
                            description: "Event type: {$typeName}. Body: {$bodyName}",
                            raw: $cell
                        );
                    });
                })
                ->values();

        } catch (ConnectionException $e) {
            throw new ExternalServiceException("AstronomyAPI unavailable", 503, $e);
        }
    }
}