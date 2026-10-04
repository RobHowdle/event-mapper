<?php

namespace FestivalMapper\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class LocationInfoController extends Controller
{
    public function settings(): JsonResponse
    {
        return response()->json([
            'what3words_enabled' => (bool) config('festival-mapper.what3words.key'),
            'elevation_enabled' => (bool) config('festival-mapper.elevation.enabled', true),
            'topography_tiles' => config('festival-mapper.topography.tiles'),
            'topography_max_zoom' => config('festival-mapper.topography.max_zoom', 17),
        ]);
    }

    public function point(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $coordinates = $data['latitude'].','.$data['longitude'];
        $result = ['what3words' => null, 'elevation' => null, 'errors' => []];
        $key = config('festival-mapper.what3words.key');
        if ($key) {
            try {
                $cacheKey = 'festival-mapper:w3w:'.hash('sha256', $key.'|'.$coordinates);
                $words = Cache::get($cacheKey);
                if (! $words) {
                    $response = Http::timeout(8)->withHeaders(['X-Api-Key' => $key])
                        ->get('https://api.what3words.com/v3/convert-to-3wa', ['coordinates' => $coordinates, 'language' => 'en']);
                    $response->throw();
                    $words = $response->json('words');
                    if (! is_string($words) || ! preg_match('/^[a-z]+\.[a-z]+\.[a-z]+$/', $words)) {
                        throw new \RuntimeException('Invalid address response');
                    }
                    Cache::put($cacheKey, $words, now()->addDay());
                }
                $result['what3words'] = ['words' => $words, 'url' => 'https://what3words.com/'.$words];
            } catch (Throwable $exception) {
                $result['errors']['what3words'] = 'Three-word addresses are temporarily unavailable.';
            }
        } else {
            $result['errors']['what3words'] = 'Three-word addresses are not available on this site yet.';
        }
        if (config('festival-mapper.elevation.enabled', true)) {
            try {
                $dataset = config('festival-mapper.elevation.dataset', 'aster30m');
                $endpoint = config('festival-mapper.elevation.endpoint', 'https://api.opentopodata.org/v1');
                $cacheKey = 'festival-mapper:elevation:'.hash('sha256', $endpoint.'|'.$dataset.'|'.$coordinates);
                $elevation = Cache::get($cacheKey);
                if (! is_array($elevation)) {
                    // Respect the public Open Topo Data API's global limits.
                    if (RateLimiter::tooManyAttempts('festival-mapper:elevation:second', 1)
                        || RateLimiter::tooManyAttempts('festival-mapper:elevation:day', 1000)) {
                        throw new \RuntimeException('Elevation request limit');
                    }
                    RateLimiter::hit('festival-mapper:elevation:second', 1);
                    RateLimiter::hit('festival-mapper:elevation:day', 86400);
                    $response = Http::timeout(8)->get(rtrim($endpoint, '/').'/'.$dataset, ['locations' => $coordinates]);
                    $response->throw();
                    $height = $response->json('results.0.elevation');
                    if ($response->json('status') !== 'OK' || ! is_numeric($height)) {
                        throw new \RuntimeException('No elevation available');
                    }
                    $elevation = ['metres' => round((float) $height, 1), 'dataset' => $dataset];
                    Cache::put($cacheKey, $elevation, now()->addDay());
                }
                $result['elevation'] = $elevation;
            } catch (Throwable $exception) {
                $result['errors']['elevation'] = 'Elevation is currently unavailable for this point. Try again shortly.';
            }
        }
        return response()->json($result);
    }

    public function grid(Request $request): JsonResponse
    {
        $data = $request->validate([
            'south' => ['required', 'numeric', 'between:-90,90'],
            'north' => ['required', 'numeric', 'between:-90,90', 'gt:south'],
            'west' => ['required', 'numeric', 'between:-180,180'],
            'east' => ['required', 'numeric', 'between:-180,180', 'gt:west'],
        ]);
        $dy = deg2rad($data['north'] - $data['south']);
        $dx = deg2rad($data['east'] - $data['west']);
        $a = sin($dy / 2) ** 2 + cos(deg2rad($data['south'])) * cos(deg2rad($data['north'])) * sin($dx / 2) ** 2;
        abort_if(6371000 * 2 * asin(min(1, sqrt($a))) > 4000, 422, 'Zoom in to see the three-metre grid.');
        $key = config('festival-mapper.what3words.key');
        if (! $key) return response()->json(['message' => 'Three-word addresses are not available on this site yet.'], 503);
        $bbox = implode(',', [$data['south'], $data['west'], $data['north'], $data['east']]);
        $cacheKey = 'festival-mapper:w3w-grid:'.hash('sha256', $key.'|'.$bbox);
        try {
            $lines = Cache::get($cacheKey);
            if (! is_array($lines)) {
                $response = Http::timeout(8)->withHeaders(['X-Api-Key' => $key])
                    ->get('https://api.what3words.com/v3/grid-section', ['bounding-box' => $bbox, 'format' => 'json']);
                $response->throw();
                $lines = $response->json('lines');
                if (! is_array($lines)) throw new \RuntimeException('Invalid grid response');
                Cache::put($cacheKey, $lines, now()->addMinutes(10));
            }
            return response()->json(['lines' => $lines]);
        } catch (Throwable $exception) {
            return response()->json(['message' => 'The what3words grid is temporarily unavailable.'], 503);
        }
    }
}
