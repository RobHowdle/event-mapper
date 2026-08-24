<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\Contracts\CoordinateTransformerInterface;
use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
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

    public function test_pixel_to_geo_loads_calibration_points_and_passes_them_to_transformer(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 0,
            'pixel_y' => 0,
            'latitude' => 54.5,
            'longitude' => -1.7,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 1200,
            'pixel_y' => 0,
            'latitude' => 54.5,
            'longitude' => -1.5,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 0,
            'pixel_y' => 800,
            'latitude' => 54.7,
            'longitude' => -1.7,
        ]);

        $transformer = new class implements CoordinateTransformerInterface {
            public array $anchors = [];

            public function toPixel(
                GeoCoordinate $geo,
                array $anchors
            ): PixelCoordinate {
                $this->anchors = $anchors;

                return new PixelCoordinate(600, 400);
            }

            public function toGeo(
                PixelCoordinate $pixel,
                array $anchors
            ): GeoCoordinate {
                $this->anchors = $anchors;

                return new GeoCoordinate(54.6, -1.6);
            }
        };

        $engine = new CoordinateEngine($transformer);

        $result = $engine->pixelToGeo(
            $festival,
            new PixelCoordinate(600, 400)
        );

        $this->assertEquals(
            new GeoCoordinate(54.6, -1.6),
            $result
        );

        $this->assertCount(
            3,
            $transformer->anchors
        );

        $this->assertSame(
            54.5,
            $transformer->anchors[0]->geo->latitude
        );

        $this->assertSame(
            -1.7,
            $transformer->anchors[0]->geo->longitude
        );

        $this->assertSame(
            1200.0,
            $transformer->anchors[1]->pixel->x
        );

        $this->assertSame(
            0.0,
            $transformer->anchors[1]->pixel->y
        );

        $this->assertSame(
            0.0,
            $transformer->anchors[2]->pixel->x
        );

        $this->assertSame(
            800.0,
            $transformer->anchors[2]->pixel->y
        );
    }

    public function test_geo_to_pixel_loads_calibration_points_and_passes_them_to_transformer(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 0,
            'pixel_y' => 0,
            'latitude' => 54.5,
            'longitude' => -1.7,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 1200,
            'pixel_y' => 0,
            'latitude' => 54.5,
            'longitude' => -1.5,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 0,
            'pixel_y' => 800,
            'latitude' => 54.7,
            'longitude' => -1.7,
        ]);

        $transformer = new class implements CoordinateTransformerInterface {
            public array $anchors = [];

            public function toPixel(
                GeoCoordinate $geo,
                array $anchors
            ): PixelCoordinate {
                $this->anchors = $anchors;

                return new PixelCoordinate(600, 400);
            }

            public function toGeo(
                PixelCoordinate $pixel,
                array $anchors
            ): GeoCoordinate {
                $this->anchors = $anchors;

                return new GeoCoordinate(54.6, -1.6);
            }
        };

        $engine = new CoordinateEngine($transformer);

        $result = $engine->geoToPixel(
            $festival,
            new GeoCoordinate(54.6, -1.6)
        );

        $this->assertEquals(
            new PixelCoordinate(600, 400),
            $result
        );

        $this->assertCount(
            3,
            $transformer->anchors
        );

        $this->assertSame(
            0.0,
            $transformer->anchors[0]->pixel->x
        );

        $this->assertSame(
            0.0,
            $transformer->anchors[0]->pixel->y
        );

        $this->assertSame(
            1200.0,
            $transformer->anchors[1]->pixel->x
        );

        $this->assertSame(
            0.0,
            $transformer->anchors[1]->pixel->y
        );

        $this->assertSame(
            0.0,
            $transformer->anchors[2]->pixel->x
        );

        $this->assertSame(
            800.0,
            $transformer->anchors[2]->pixel->y
        );
    }
}
