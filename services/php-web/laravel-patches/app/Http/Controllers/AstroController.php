<?php

namespace App\Http\Controllers;

use App\Contracts\AstronomyClientInterface;
use App\Http\Requests\GetAstroEventsRequest;
use App\Jobs\UpdateAstronomyCacheJob;
use App\Http\Resources\AstroEventResource;
use Illuminate\Support\Facades\Cache;

class AstroController extends Controller
{
    public function __construct(
        private AstronomyClientInterface $astroClient
    ) {}

    public function events(GetAstroEventsRequest $request)
    {
        $data = $request->validated();
        $lat = (float)($data['lat'] ?? 55.75);
        $lon = (float)($data['lon'] ?? 37.61);
        $days = 7;

        $key = "astro_events:{$lat}:{$lon}:{$days}:" . now()->format('Y-m-d');
        if (Cache::has($key)) {
            $events = $this->astroClient->getEvents($lat, $lon, $days);
            return response()->json([
                'status' => 'ready',
                'data' => AstroEventResource::collection($events)
            ]);
        }

        UpdateAstronomyCacheJob::dispatch($lat, $lon);

        return response()->json([
            'status' => 'processing',
            'message' => 'Data is being fetched in background. Please retry in 5 seconds.',
            'retry_after' => 5
        ], 202);
    }
}