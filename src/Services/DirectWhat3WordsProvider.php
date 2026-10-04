<?php

namespace FestivalMapper\Services;

use FestivalMapper\Contracts\What3WordsProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DirectWhat3WordsProvider implements What3WordsProvider
{
    public function enabled(): bool
    {
        return (bool) config('festival-mapper.what3words.key');
    }

    private function key(): string
    {
        $key = config('festival-mapper.what3words.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Three-word addresses are not configured.', 1000);
        }
        return $key;
    }

    public function address(float $latitude, float $longitude): array
    {
        $key = $this->key();
        $coordinates = $latitude.','.$longitude;
        $cacheKey = 'festival-mapper:w3w:'.hash('sha256', $key.'|'.$coordinates);
        $words = Cache::get($cacheKey);
        if (! $words) {
            $response = Http::timeout(8)->withHeaders(['X-Api-Key' => $key])
                ->get('https://api.what3words.com/v3/convert-to-3wa', ['coordinates' => $coordinates, 'language' => 'en']);
            if (! $response->successful()) {
                throw new RuntimeException('Three-word address lookup failed.', $this->failureCode($response->status()));
            }
            $words = $response->json('words');
            if (! is_string($words) || ! preg_match('/^[a-z]+\.[a-z]+\.[a-z]+$/', $words)) {
                throw new RuntimeException('Invalid three-word address response.');
            }
            Cache::put($cacheKey, $words, now()->addDay());
        }
        return ['words' => $words, 'url' => 'https://what3words.com/'.$words];
    }

    public function grid(array $bounds): array
    {
        $key = $this->key();
        $bbox = implode(',', [$bounds['south'], $bounds['west'], $bounds['north'], $bounds['east']]);
        $cacheKey = 'festival-mapper:w3w-grid:'.hash('sha256', $key.'|'.$bbox);
        $lines = Cache::get($cacheKey);
        if (! is_array($lines)) {
            $response = Http::timeout(8)->withHeaders(['X-Api-Key' => $key])
                ->get('https://api.what3words.com/v3/grid-section', ['bounding-box' => $bbox, 'format' => 'json']);
            if (! $response->successful()) {
                throw new RuntimeException('Three-word grid lookup failed.', $this->failureCode($response->status()));
            }
            if (! is_array($response->json('lines'))) {
                throw new RuntimeException('Three-word grid lookup failed.');
            }
            $lines = $response->json('lines');
            Cache::put($cacheKey, $lines, now()->addMinutes(10));
        }
        return ['lines' => $lines];
    }
    private function failureCode(int $status): int
    {
        return match ($status) { 401 => 1003, 402 => 1004, 429 => 1005, default => 1007 };
    }

}
