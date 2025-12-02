<?php

namespace App\Jobs;

use App\Contracts\AstronomyClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateAstronomyCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 3;

    public function __construct(
        protected float $lat,
        protected float $lon
    ) {}

    public function handle(AstronomyClientInterface $client): void
    {
        Log::info("Job started: Updating astronomy cache for {$this->lat}, {$this->lon}");

        $days = 7;
        $key = "astro_events:{$this->lat}:{$this->lon}:{$days}:" . now()->format('Y-m-d');
        
        try {
            Cache::forget($key);

            $events = $client->getEvents($this->lat, $this->lon, $days);

            Log::info("Job finished: Cache updated, found " . $events->count() . " events.");

        } catch (\Throwable $e) {
             Log::error("Astro Job failed during execution: " . $e->getMessage(), [
                 'exception' => get_class($e),
                 'trace' => $e->getTraceAsString(),
                 'context' => [
                     'lat' => $this->lat,
                     'lon' => $this->lon,
                 ]
             ]);
             throw $e; 
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed (Queue Worker): " . $exception->getMessage());
    }
}