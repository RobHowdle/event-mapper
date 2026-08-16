<?php

namespace FestivalMapper\Tests\Unit;

use FestivalMapper\Transforms\AffineTransformer;
use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AffineTransformerTest extends TestCase
{
    private AffineTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new AffineTransformer();
    }

    /**
     * Two calibration points defining a simple geographic transform.
     *
     * Pixel:
     *   (0, 0)       -> (54.000000, -1.000000)
     *   (1000, 1000) -> (54.010000, -0.990000)
     */
    private function anchors(): array
    {
        return [
            new CalibrationAnchor(
                new PixelCoordinate(0.0, 0.0),
                new GeoCoordinate(
                    latitude: 54.000000,
                    longitude: -1.000000,
                ),
            ),
            new CalibrationAnchor(
                new PixelCoordinate(1000.0, 1000.0),
                new GeoCoordinate(
                    latitude: 54.010000,
                    longitude: -0.990000,
                ),
            ),
        ];
    }

    #[Test]
    public function it_converts_pixel_to_geo_at_origin(): void
    {
        $result = $this->transformer->toGeo(
            new PixelCoordinate(0.0, 0.0),
            $this->anchors()
        );

        $this->assertEqualsWithDelta(54.000000, $result->latitude, 1e-9);
        $this->assertEqualsWithDelta(-1.000000, $result->longitude, 1e-9);
    }

    #[Test]
    public function it_converts_pixel_to_geo_at_far_corner(): void
    {
        $result = $this->transformer->toGeo(
            new PixelCoordinate(1000.0, 1000.0),
            $this->anchors()
        );

        $this->assertEqualsWithDelta(54.010000, $result->latitude, 1e-9);
        $this->assertEqualsWithDelta(-0.990000, $result->longitude, 1e-9);
    }

    #[Test]
    public function it_converts_pixel_to_geo_at_midpoint(): void
    {
        $result = $this->transformer->toGeo(
            new PixelCoordinate(500.0, 500.0),
            $this->anchors()
        );

        $this->assertEqualsWithDelta(54.005000, $result->latitude, 1e-9);
        $this->assertEqualsWithDelta(-0.995000, $result->longitude, 1e-9);
    }

    #[Test]
    public function it_round_trips_pixel_through_geo_and_back(): void
    {
        $original = new PixelCoordinate(347.0, 812.0);

        $geo = $this->transformer->toGeo(
            $original,
            $this->anchors()
        );

        $restored = $this->transformer->toPixel(
            $geo,
            $this->anchors()
        );

        $this->assertEqualsWithDelta($original->x, $restored->x, 1e-6);
        $this->assertEqualsWithDelta($original->y, $restored->y, 1e-6);
    }

    #[Test]
    public function it_round_trips_geo_through_pixel_and_back(): void
    {
        $original = new GeoCoordinate(
            latitude: 54.003000,
            longitude: -0.997000,
        );

        $pixel = $this->transformer->toPixel(
            $original,
            $this->anchors()
        );

        $restored = $this->transformer->toGeo(
            $pixel,
            $this->anchors()
        );

        $this->assertEqualsWithDelta(
            $original->latitude,
            $restored->latitude,
            1e-6
        );

        $this->assertEqualsWithDelta(
            $original->longitude,
            $restored->longitude,
            1e-6
        );
    }

    #[Test]
    public function it_throws_when_fewer_than_two_anchors_are_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->transformer->toGeo(
            new PixelCoordinate(100.0, 100.0),
            [
                new CalibrationAnchor(
                    new PixelCoordinate(0.0, 0.0),
                    new GeoCoordinate(
                        latitude: 54.000000,
                        longitude: -1.000000,
                    ),
                ),
            ]
        );
    }

    #[Test]
    public function it_throws_when_pixel_anchors_are_coincident(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $duplicateAnchor = new CalibrationAnchor(
            new PixelCoordinate(100.0, 100.0),
            new GeoCoordinate(
                latitude: 54.005000,
                longitude: -0.995000,
            ),
        );

        $this->transformer->toGeo(
            new PixelCoordinate(50.0, 50.0),
            [$duplicateAnchor, $duplicateAnchor]
        );
    }

    #[Test]
    public function it_throws_when_geo_anchors_are_coincident(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $anchor1 = new CalibrationAnchor(
            new PixelCoordinate(0.0, 0.0),
            new GeoCoordinate(
                latitude: 54.000000,
                longitude: -1.000000,
            ),
        );

        $anchor2 = new CalibrationAnchor(
            new PixelCoordinate(1000.0, 1000.0),
            new GeoCoordinate(
                latitude: 54.000000,
                longitude: -1.000000,
            ),
        );

        $this->transformer->toPixel(
            new GeoCoordinate(
                latitude: 54.005000,
                longitude: -0.995000,
            ),
            [$anchor1, $anchor2]
        );
    }

    #[Test]
    public function it_handles_non_axis_aligned_transform(): void
    {
        // Moving 1000 pixels vertically corresponds to moving
        // east by 0.01 degrees of longitude.
        $anchors = [
            new CalibrationAnchor(
                new PixelCoordinate(0.0, 0.0),
                new GeoCoordinate(
                    latitude: 54.000000,
                    longitude: -1.000000,
                ),
            ),
            new CalibrationAnchor(
                new PixelCoordinate(0.0, 1000.0),
                new GeoCoordinate(
                    latitude: 54.000000,
                    longitude: -0.990000,
                ),
            ),
        ];

        $geo = $this->transformer->toGeo(
            new PixelCoordinate(0.0, 500.0),
            $anchors
        );

        $this->assertEqualsWithDelta(
            54.000000,
            $geo->latitude,
            1e-9
        );

        $this->assertEqualsWithDelta(
            -0.995000,
            $geo->longitude,
            1e-9
        );
    }
}
