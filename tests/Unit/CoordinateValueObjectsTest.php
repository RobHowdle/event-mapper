<?php

namespace Tests\Unit;

use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use PHPUnit\Framework\TestCase;

class CoordinateValueObjectsTest extends TestCase
{
    public function test_geo_coordinate_serializes_and_restores(): void
    {
        $coordinate = new GeoCoordinate(
            latitude: 54.123456,
            longitude: -1.234567,
        );

        $this->assertSame([
            'latitude' => 54.123456,
            'longitude' => -1.234567,
        ], $coordinate->toArray());

        $restored = GeoCoordinate::fromArray($coordinate->toArray());

        $this->assertSame($coordinate->latitude, $restored->latitude);
        $this->assertSame($coordinate->longitude, $restored->longitude);
    }

    public function test_pixel_coordinate_serializes_and_restores(): void
    {
        $coordinate = new PixelCoordinate(
            x: 1234.5,
            y: 678.25,
        );

        $this->assertSame([
            'x' => 1234.5,
            'y' => 678.25,
        ], $coordinate->toArray());

        $restored = PixelCoordinate::fromArray($coordinate->toArray());

        $this->assertSame($coordinate->x, $restored->x);
        $this->assertSame($coordinate->y, $restored->y);
    }

    public function test_calibration_anchor_serializes_and_restores(): void
    {
        $anchor = new CalibrationAnchor(
            pixel: new PixelCoordinate(100.5, 200.25),
            geo: new GeoCoordinate(54.123456, -1.234567),
        );

        $this->assertSame([
            'pixel' => [
                'x' => 100.5,
                'y' => 200.25,
            ],
            'geo' => [
                'latitude' => 54.123456,
                'longitude' => -1.234567,
            ],
        ], $anchor->toArray());

        $restored = CalibrationAnchor::fromArray($anchor->toArray());

        $this->assertSame(
            $anchor->pixel->x,
            $restored->pixel->x
        );

        $this->assertSame(
            $anchor->pixel->y,
            $restored->pixel->y
        );

        $this->assertSame(
            $anchor->geo->latitude,
            $restored->geo->latitude
        );

        $this->assertSame(
            $anchor->geo->longitude,
            $restored->geo->longitude
        );
    }
}
