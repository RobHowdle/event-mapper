<?php

namespace FestivalMapper\Tests\Unit;

use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\CalibrationPoint;
use FestivalMapper\Models\Festival;
use FestivalMapper\Transforms\AffineTransformer;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class CoordinateEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    public function test_pixel_to_geo_uses_festival_calibration_points(): void
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

        $engine = new CoordinateEngine(
            new AffineTransformer()
        );

        $result = $engine->pixelToGeo(
            $festival,
            new PixelCoordinate(500, 500)
        );

        $this->assertEqualsWithDelta(
            54.005000,
            $result->latitude,
            1e-9
        );

        $this->assertEqualsWithDelta(
            -0.995000,
            $result->longitude,
            1e-9
        );
    }

    public function test_geo_to_pixel_uses_festival_calibration_points(): void
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

        $engine = new CoordinateEngine(
            new AffineTransformer()
        );

        $result = $engine->geoToPixel(
            $festival,
            new GeoCoordinate(
                latitude: 54.005000,
                longitude: -0.995000,
            )
        );

        $this->assertEqualsWithDelta(
            500,
            $result->x,
            1e-6
        );

        $this->assertEqualsWithDelta(
            500,
            $result->y,
            1e-6
        );
    }
}
