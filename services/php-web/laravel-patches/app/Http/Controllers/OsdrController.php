<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OsdrController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->integer('limit', 20); 
        if ($limit < 1) $limit = 20;

        $base = getenv('RUST_BASE') ?: 'http://rust_iss:3000';

        $url = $base.'/osdr/list?limit='.$limit;
        $json = @file_get_contents($url);

        $data = $json ? json_decode($json, true) : ['items' => []];
        $items = $data['items'] ?? [];

        $items = $this->flattenOsdr($items);

        return view('osdr', [
            'items' => $items,
            'currentLimit' => $limit,
            'src'   => $url,
        ]);
    }

    private function flattenOsdr(array $items): array
    {
        $out = [];
        foreach ($items as $row) {
            $raw = $row['Raw'] ?? [];
            
            if (is_array($raw) && $this->looksOsdrDict($raw)) {
                
                foreach ($raw as $k => $v) {
                    if (!is_array($v)) continue;
                    
                    $rest = $v['REST_URL'] ?? $v['rest_url'] ?? $v['rest'] ?? null;
                    $title = $v['Title'] ?? $v['Name'] ?? $v['title'] ?? $v['name'] ?? null;

                    if (!$title) {
                        $title = $k;
                    }
                    if (!$title && is_string($rest)) {
                        $title = basename(rtrim($rest, '/'));
                    }
                    
                    $out[] = [
                        'id'            => $row['ID'] ?? null,
                        'dataset_id'    => $k,
                        'title'         => $title,
                        'status'        => $row['Status'] ?? null,
                        'updated_at'    => $row['UpdatedAt'] ?? null,
                        'inserted_at'   => $row['InsertedAt'] ?? null,
                        'rest_url'      => $rest,
                        'raw'           => $v,
                    ];
                }
            } else {
                $row['rest_url'] = is_array($raw) ? ($raw['REST_URL'] ?? $raw['rest_url'] ?? null) : null;
                $out[] = $row;
            }
        }
        return $out;
    }

    private function looksOsdrDict(array $raw): bool
    {
        foreach ($raw as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'OSD-')) return true;
            if (is_array($v) && (isset($v['REST_URL']) || isset($v['rest_url']))) return true;
        }
        return false;
    }
}