<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\CalibrationPoint;
use FestivalMapper\Models\Festival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class CoordinateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    private function createFestivalWithCalibration(): Festival
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        CalibrationPoint::create([
            'festival_id' => $festival->id,
            'pixel_x' => 0,
            'pixel_y' => 0,
            'latitude' => 54.000000,
            'longitude' => -1.000000,
        ]);

        CalibrationPoint::create([
            'festival_id' => $festival->id,
            'pixel_x' => 1000,
            'pixel_y' => 0,
            'latitude' => 54.000000,
            'longitude' => -0.990000,
        ]);

        CalibrationPoint::create([
            'festival_id' => $festival->id,
            'pixel_x' => 0,
            'pixel_y' => 1000,
            'latitude' => 54.010000,
            'longitude' => -1.000000,
        ]);

        return $festival;
    }

    public function test_geo_to_pixel_endpoint_returns_pixel_coordinate(): void
    {
        $festival = $this->createFestivalWithCalibration();

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/coordinates/to-pixel",
            [
                'latitude' => 54.005000,
                'longitude' => -0.995000,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'geo' => ['latitude', 'longitude'],
                'pixel' => ['x', 'y'],
            ])
            ->assertJsonPath('geo.latitude', 54.005)
            ->assertJsonPath('geo.longitude', -0.995);

        $json = $response->json();

        $this->assertEqualsWithDelta(
            500.0,
            $json['pixel']['x'],
            1e-6
        );

        $this->assertEqualsWithDelta(
            500.0,
            $json['pixel']['y'],
            1e-6
        );
    }

    public function test_pixel_to_geo_endpoint_returns_geographic_coordinate(): void
    {
        $festival = $this->createFestivalWithCalibration();

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/coordinates/to-geo",
            [
                'x' => 500,
                'y' => 500,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'pixel' => ['x', 'y'],
                'geo' => ['latitude', 'longitude'],
            ])
            ->assertJsonPath('pixel.x', 500)
            ->assertJsonPath('pixel.y', 500);

        $json = $response->json();

        $this->assertEqualsWithDelta(
            54.005,
            $json['geo']['latitude'],
            0.000000001
        );

        $this->assertEqualsWithDelta(
            -0.995,
            $json['geo']['longitude'],
            0.000000001
        );
    }

    public function test_geo_to_pixel_endpoint_validates_coordinates(): void
    {
        $festival = $this->createFestivalWithCalibration();

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/coordinates/to-pixel",
            [
                'latitude' => 91,
                'longitude' => -181,
            ]
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'latitude',
            'longitude',
        ]);
    }

    public function test_pixel_to_geo_endpoint_requires_pixel_coordinates(): void
    {
        $festival = $this->createFestivalWithCalibration();

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/coordinates/to-geo",
            []
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'x',
            'y',
        ]);
    }
}
