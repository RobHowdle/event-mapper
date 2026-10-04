<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\FestivalMapperServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Orchestra\Testbench\TestCase;

class LocationInfoTest extends TestCase
{
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

    public function test_point_lookup_returns_cached_address_and_elevation_without_exposing_key(): void
    {
        config(['festival-mapper.what3words.key' => 'test-secret-key']);
        Http::fake([
            'api.what3words.com/*' => Http::response(['words' => 'filled.count.soap']),
            'api.opentopodata.org/*' => Http::response(['status' => 'OK', 'results' => [['elevation' => 146.4]]]),
        ]);
        $path = '/api/festival-mapper/location-info/point?latitude=52.83&longitude=-1.39';
        $response = $this->getJson($path)->assertOk()
            ->assertJsonPath('what3words.words', 'filled.count.soap')
            ->assertJsonPath('elevation.metres', 146.4);
        $this->assertStringNotContainsString('test-secret-key', $response->getContent());
        $this->getJson($path)->assertOk()->assertJsonPath('what3words.words', 'filled.count.soap');
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'what3words') && $request->hasHeader('X-Api-Key', 'test-secret-key'));
        $settings = $this->getJson('/api/festival-mapper/location-info/settings')->assertOk();
        $this->assertStringNotContainsString('test-secret-key', $settings->getContent());
    }

    public function test_missing_key_and_provider_failure_are_reported_without_fake_results(): void
    {
        config(['festival-mapper.what3words.key' => null]);
        Http::fake(['api.opentopodata.org/*' => Http::response([], 503)]);
        $this->getJson('/api/festival-mapper/location-info/point?latitude=52.83&longitude=-1.39')
            ->assertOk()->assertJsonPath('what3words', null)->assertJsonPath('elevation', null)
            ->assertJsonStructure(['errors' => ['what3words', 'elevation']]);
    }

    public function test_coordinates_and_grid_size_are_validated_before_provider_calls(): void
    {
        $this->getJson('/api/festival-mapper/location-info/point?latitude=95&longitude=0')
            ->assertUnprocessable();
        $this->getJson('/api/festival-mapper/location-info/grid?south=51&north=53&west=-2&east=0')
            ->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_grid_requests_are_cached_and_require_a_key(): void
    {
        $path = '/api/festival-mapper/location-info/grid?south=52.83&north=52.8301&west=-1.39&east=-1.3899';
        config(['festival-mapper.what3words.key' => null]);
        $this->getJson($path)->assertStatus(503);
        config(['festival-mapper.what3words.key' => 'test-secret-key']);
        Http::fake(['api.what3words.com/*' => Http::response(['lines' => []])]);
        $this->getJson($path)->assertOk()->assertJsonPath('lines', []);
        $this->getJson($path)->assertOk();
        Http::assertSentCount(1);
    }
    public function test_host_can_supply_a_remote_provider_without_a_local_key(): void
    {
        config(['festival-mapper.what3words.key' => null, 'festival-mapper.elevation.enabled' => false]);
        $this->app->instance(\FestivalMapper\Contracts\What3WordsProvider::class, new class implements \FestivalMapper\Contracts\What3WordsProvider {
            public function enabled(): bool { return true; }
            public function address(float $latitude, float $longitude): array
            {
                return ['words' => 'filled.count.soap', 'url' => 'https://what3words.com/filled.count.soap'];
            }
            public function grid(array $bounds): array { return ['lines' => []]; }
        });
        $this->getJson('/api/festival-mapper/location-info/settings')->assertOk()->assertJsonPath('what3words_enabled', true);
        $this->getJson('/api/festival-mapper/location-info/point?latitude=52.83&longitude=-1.39')
            ->assertOk()->assertJsonPath('what3words.words', 'filled.count.soap');
        $this->getJson('/api/festival-mapper/location-info/grid?south=52.83&north=52.8301&west=-1.39&east=-1.3899')
            ->assertOk()->assertJsonPath('lines', []);
        Http::assertNothingSent();
    }

}
