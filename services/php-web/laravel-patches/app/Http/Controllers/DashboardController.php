<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $iss = Cache::remember('iss_last_position', 10, function () {
            $base = getenv('RUST_BASE') ?: 'http://go_iss:3000';
            try {
                return Http::timeout(2)->get("$base/last")->json() ?? [];
            } catch (\Exception $e) {
                return [];
            }
        });

        return view('dashboard', [
            'iss' => $iss,
            'metrics' => [
                'iss_speed' => $iss['payload']['velocity'] ?? null,
                'iss_alt'   => $iss['payload']['altitude'] ?? null,
                'neo_total' => 0,
            ],
            'trend' => [],
            'jw_gallery' => [],
            'jw_observation_raw' => [], 
            'jw_observation_summary' => [],
            'jw_observation_images' => [], 
            'jw_observation_files' => [],
        ]);
    }

}