<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Orchestra\Testbench\TestCase;

class TerrainTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [FestivalMapperServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        RateLimiter::clear('festival-mapper:elevation:second');
        RateLimiter::clear('festival-mapper:elevation:day');
        Http::preventStrayRequests();
    }

    private function calibratedFestival(): Festival
    {
        $festival = Festival::create(['name' => 'Terrain festival', 'year' => 2026, 'map_width' => 1000, 'map_height' => 1000]);
        $this->mock(CoordinateEngine::class, function ($mock) {
            $mock->shouldReceive('pixelToGeo')->andReturnUsing(fn ($festival, $pixel) => new GeoCoordinate(52.84 - $pixel->y * .00001, -1.4 + $pixel->x * .00001));
        });
        return $festival;
    }

    public function test_calibrated_site_uses_one_cached_batch_and_fixed_real_elevation_range(): void
    {
        $festival = $this->calibratedFestival();
        $results = array_map(fn ($i) => ['elevation' => $i === 5 ? null : 100 + $i], range(0, 99));
        Http::fake(['api.opentopodata.org/*' => Http::response(['status' => 'OK', 'results' => $results])]);
        $path = "/api/festival-mapper/festivals/{$festival->id}/terrain";
        $this->getJson($path)->assertOk()->assertJsonPath('minimum', 100)->assertJsonPath('maximum', 199)
            ->assertJsonPath('rows', 10)->assertJsonPath('columns', 10)->assertJsonPath('heights.5', null);
        $this->getJson($path)->assertOk()->assertJsonPath('minimum', 100)->assertJsonPath('maximum', 199);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && count(explode('|', $request['locations'])) === 100);
    }

    public function test_uncalibrated_map_returns_actionable_error_without_provider_call(): void
    {
        $festival = Festival::create(['name' => 'Uncalibrated', 'year' => 2026, 'map_width' => 1000, 'map_height' => 1000]);
        $this->getJson("/api/festival-mapper/festivals/{$festival->id}/terrain")->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_missing_terrain_does_not_return_fake_elevation_or_legend(): void
    {
        $festival = $this->calibratedFestival();
        Http::fake(['api.opentopodata.org/*' => Http::response(['status' => 'OK', 'results' => array_fill(0, 100, ['elevation' => null])])]);
        $this->getJson("/api/festival-mapper/festivals/{$festival->id}/terrain")->assertStatus(503)->assertJsonMissing(['minimum' => 0]);
    }
}
