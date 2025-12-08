<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Support\JwstHelper;
use App\Providers\ExternalApiServiceProvider;
use App\Contracts\AstronomyClientInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __construct(
        private AstronomyClientInterface $astroClient
    ) {}

    public function index()
    {
        $iss = Cache::remember('iss_last_position', 10, function () {
            $base = getenv('RUST_BASE') ?: 'http://go_iss:3000';
            try {
                $data = Http::timeout(2)->get("$base/last")->json() ?? [];
                return array_change_key_case($data, CASE_LOWER);
            } catch (\Exception $e) {
                return [];
            }
        });

        $trend = Cache::remember('iss_trend_data', 30, function () {
            $base = getenv('RUST_BASE') ?: 'http://go_iss:3000';
            try {
                return Http::timeout(2)->get("$base/iss/trend")->json() ?? [];
            } catch (\Exception $e) {
                return [];
            }
        });
        
        $lat = $iss['payload']['latitude'] ?? 55.75;
        $lon = $iss['payload']['longitude'] ?? 37.61;

        $astroKey = "astro_dashboard:".round($lat, 1).":".round($lon, 1);
        $astroEventsArray = Cache::remember($astroKey, 3600, function () use ($lat, $lon) {
            try {
                \Log::info('Fetching astro events', ['lat' => $lat, 'lon' => $lon]);
                
                $events = $this->astroClient->getEvents($lat, $lon, 365);
                
                \Log::info('Astro events fetched', ['count' => $events->count()]);
                
                return collect($events)->map(function ($e) {
                    return [
                        'name' => data_get($e, 'name'),
                        'date' => data_get($e, 'date'),
                        'description' => data_get($e, 'description'),
                        'raw' => data_get($e, 'raw'),
                    ];
                })->values()->all();
            } catch (\Exception $e) {
                \Log::error('Astro API failed: '.$e->getMessage(), [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);
                return [];
            }
        });

        $astroEvents = collect($astroEventsArray)->map(fn($item) => is_array($item) ? (object)$item : $item);

        $jwstItems = Cache::remember('jwst_feed_dashboard', 300, function () {
            $jw = new JwstHelper();
            $resp = $jw->get('all/type/jpg', ['page'=>1, 'perPage'=>6]);
            $list = $resp['body'] ?? ($resp['data'] ?? []);
            
            $cleanItems = [];
            foreach ($list as $it) {
                $url = JwstHelper::pickImageUrl($it);
                if (!$url) continue;
                
                $cleanItems[] = [
                    'url' => $url,
                    'title' => $it['details']['mission'] ?? $it['program'] ?? 'JWST Image',
                    'id' => $it['id'] ?? uniqid(),
                ];
            }
            return $cleanItems;
        });

        return view('dashboard', [
            'iss' => $iss,
            'trend' => $trend,
            'astroEvents' => $astroEvents,
            'jwstItems' => $jwstItems,
        ]);
    }

    public function jwstFeed(Request $r)
    {
        $src   = $r->query('source', 'jpg');
        $sfx   = trim((string)$r->query('suffix', ''));
        $prog  = trim((string)$r->query('program', ''));
        $instF = strtoupper(trim((string)$r->query('instrument', '')));
        $page  = max(1, (int)$r->query('page', 1));
        $per   = max(1, min(60, (int)$r->query('perPage', 24)));
        $jw = new JwstHelper();

        $path = 'all/type/jpg';
        if ($src === 'suffix' && $sfx !== '') $path = 'all/suffix/'.ltrim($sfx,'/');
        if ($src === 'program' && $prog !== '') $path = 'program/id/'.rawurlencode($prog);
        $resp = $jw->get($path, ['page'=>$page, 'perPage'=>$per]);
        $list = $resp['body'] ?? ($resp['data'] ?? (is_array($resp) ? $resp : []));
        $items = [];
        foreach ($list as $it) {
            if (!is_array($it)) continue;
            $url = null;
            $loc = $it['location'] ?? $it['url'] ?? null;
            $thumb = $it['thumbnail'] ?? null;
            foreach ([$loc, $thumb] as $u) {
                if (is_string($u) && preg_match('~\.(jpg|jpeg|png)(\?.*)?$~i', $u)) { $url = $u; break; }
            }
            if (!$url) {
                $url = \App\Support\JwstHelper::pickImageUrl($it);
            }
            if (!$url) continue;
            $instList = [];
            foreach (($it['details']['instruments'] ?? []) as $I) {
                if (is_array($I) && !empty($I['instrument'])) $instList[] = strtoupper($I['instrument']);
            }
            if ($instF && $instList && !in_array($instF, $instList, true)) continue;
            $items[] = [
                'url'      => $url,
                'obs'      => (string)($it['observation_id'] ?? $it['observationId'] ?? ''),
                'program'  => (string)($it['program'] ?? ''),
                'suffix'   => (string)($it['details']['suffix'] ?? $it['suffix'] ?? ''),
                'inst'     => $instList,
                'caption'  => trim(
                    (($it['observation_id'] ?? '') ?: ($it['id'] ?? '')) .
                    ' · P' . ($it['program'] ?? '-') .
                    (($it['details']['suffix'] ?? '') ? ' · ' . $it['details']['suffix'] : '') .
                    ($instList ? ' · ' . implode('/', $instList) : '')
                ),
                'link'     => $loc ?: $url,
            ];
            if (count($items) >= $per) break;
        }
        return parent::jwstFeed($r);
    }
}