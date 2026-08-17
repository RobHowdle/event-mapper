<?php

namespace FestivalMapper\Tests\Unit;

use FestivalMapper\Models\CalibrationPoint;
use FestivalMapper\Models\Pin;
use FestivalMapper\ValueObjects\GeoCoordinate;
use PHPUnit\Framework\TestCase;

class CoordinateModelTest extends TestCase
{
    public function test_calibration_point_converts_to_calibration_anchor(): void
    {
        $point = new CalibrationPoint([
            'pixel_x' => 123.5,
            'pixel_y' => 456.75,
            'latitude' => 54.123456,
            'longitude' => -1.234567,
        ]);

        $anchor = $point->toAnchor();

        $this->assertSame(123.5, $anchor->pixel->x);
        $this->assertSame(456.75, $anchor->pixel->y);
        $this->assertSame(54.123456, $anchor->geo->latitude);
        $this->assertSame(-1.234567, $anchor->geo->longitude);
    }

    public function test_pin_converts_to_geo_coordinate(): void
    {
        $pin = new Pin([
            'latitude' => 54.123456,
            'longitude' => -1.234567,
        ]);

        $coordinate = $pin->toCoordinate();

        $this->assertInstanceOf(GeoCoordinate::class, $coordinate);
        $this->assertSame(54.123456, $coordinate->latitude);
        $this->assertSame(-1.234567, $coordinate->longitude);
    }
}
