<?php

namespace FestivalMapper\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class ElevationService
{
    public function samples(array $points): array
    {
        if (! config('festival-mapper.elevation.enabled', true) || count($points) < 1 || count($points) > 100) {
            throw new RuntimeException('Elevation is unavailable.');
        }
        $dataset = config('festival-mapper.elevation.dataset', 'aster30m');
        $endpoint = rtrim(config('festival-mapper.elevation.endpoint', 'https://api.opentopodata.org/v1'), '/');
        $locations = implode('|', array_map(fn ($point) => $point['latitude'].','.$point['longitude'], $points));
        $cacheKey = 'festival-mapper:elevation-samples:'.hash('sha256', $endpoint.'|'.$dataset.'|'.$locations);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached;

        // Serialize uncached calls so concurrent viewers cannot exceed the public limit.
        $result = Cache::lock('festival-mapper:elevation-provider', 15)->get(function () use ($cacheKey, $endpoint, $dataset, $locations, $points) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) return $cached;
            if (RateLimiter::tooManyAttempts('festival-mapper:elevation:second', 1)
                || RateLimiter::tooManyAttempts('festival-mapper:elevation:day', 1000)) {
                throw new RuntimeException('Elevation request limit reached.');
            }
            RateLimiter::hit('festival-mapper:elevation:second', 1);
            RateLimiter::hit('festival-mapper:elevation:day', 86400);
            $http = Http::timeout(8)->acceptJson();
            $response = count($points) === 1
                ? $http->get($endpoint.'/'.$dataset, ['locations' => $locations])
                : $http->post($endpoint.'/'.$dataset, ['locations' => $locations, 'interpolation' => 'bilinear']);
            $results = $response->json('results');
            if (! $response->successful() || $response->json('status') !== 'OK'
                || ! is_array($results) || count($results) !== count($points)) {
                throw new RuntimeException('Elevation lookup failed.');
            }
            $heights = array_map(fn ($item) => is_numeric($item['elevation'] ?? null) ? round((float) $item['elevation'], 1) : null, $results);
            $result = ['heights' => $heights, 'dataset' => $dataset];
            Cache::put($cacheKey, $result, now()->addDay());
            return $result;
        });
        if (! is_array($result)) throw new RuntimeException('Elevation provider is busy.');
        return $result;
    }

    public function point(float $latitude, float $longitude): array
    {
        $result = $this->samples([['latitude' => $latitude, 'longitude' => $longitude]]);
        if ($result['heights'][0] === null) throw new RuntimeException('No elevation available.');
        return ['metres' => $result['heights'][0], 'dataset' => $result['dataset']];
    }
}
