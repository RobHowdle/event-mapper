<?php

namespace FestivalMapper\Tests\Unit;

use FestivalMapper\Transforms\AffineTransformer;
use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\InternalCoordinate;
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

    private function anchors(): array
    {
        return [
            new CalibrationAnchor(
                new PixelCoordinate(0.0, 0.0),
                new InternalCoordinate(0.0, 0.0),
            ),
            new CalibrationAnchor(
                new PixelCoordinate(1000.0, 1000.0),
                new InternalCoordinate(1.0, 1.0),
            ),
        ];
    }

    #[Test]
    public function it_converts_pixel_to_internal_at_origin(): void
    {
        $result = $this->transformer->toInternal(
            new PixelCoordinate(0.0, 0.0),
            $this->anchors()
        );

        $this->assertEqualsWithDelta(0.0, $result->x, 1e-9);
        $this->assertEqualsWithDelta(0.0, $result->y, 1e-9);
    }

    #[Test]
    public function it_converts_pixel_to_internal_at_far_corner(): void
    {
        $result = $this->transformer->toInternal(
            new PixelCoordinate(1000.0, 1000.0),
            $this->anchors()
        );

        $this->assertEqualsWithDelta(1.0, $result->x, 1e-9);
        $this->assertEqualsWithDelta(1.0, $result->y, 1e-9);
    }

    #[Test]
    public function it_converts_pixel_to_internal_at_midpoint(): void
    {
        $result = $this->transformer->toInternal(
            new PixelCoordinate(500.0, 500.0),
            $this->anchors()
        );

        $this->assertEqualsWithDelta(0.5, $result->x, 1e-9);
        $this->assertEqualsWithDelta(0.5, $result->y, 1e-9);
    }

    #[Test]
    public function it_round_trips_pixel_through_internal_and_back(): void
    {
        $original = new PixelCoordinate(347.0, 812.0);

        $internal = $this->transformer->toInternal($original, $this->anchors());
        $restored = $this->transformer->toPixel($internal, $this->anchors());

        $this->assertEqualsWithDelta($original->x, $restored->x, 1e-6);
        $this->assertEqualsWithDelta($original->y, $restored->y, 1e-6);
    }

    #[Test]
    public function it_round_trips_internal_through_pixel_and_back(): void
    {
        $original = new InternalCoordinate(0.3, 0.7);

        $pixel    = $this->transformer->toPixel($original, $this->anchors());
        $restored = $this->transformer->toInternal($pixel, $this->anchors());

        $this->assertEqualsWithDelta($original->x, $restored->x, 1e-6);
        $this->assertEqualsWithDelta($original->y, $restored->y, 1e-6);
    }

    #[Test]
    public function it_throws_when_fewer_than_two_anchors_are_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->transformer->toInternal(
            new PixelCoordinate(100.0, 100.0),
            [
                new CalibrationAnchor(
                    new PixelCoordinate(0.0, 0.0),
                    new InternalCoordinate(0.0, 0.0),
                ),
            ]
        );
    }

    #[Test]
    public function it_throws_when_anchors_are_coincident(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $duplicateAnchor = new CalibrationAnchor(
            new PixelCoordinate(100.0, 100.0),
            new InternalCoordinate(0.5, 0.5),
        );

        $this->transformer->toInternal(
            new PixelCoordinate(50.0, 50.0),
            [$duplicateAnchor, $duplicateAnchor]
        );
    }

    #[Test]
    public function it_handles_non_axis_aligned_transform(): void
    {
        // Anchors define a rotated coordinate system.
        $anchors = [
            new CalibrationAnchor(
                new PixelCoordinate(0.0, 0.0),
                new InternalCoordinate(0.0, 0.0),
            ),
            new CalibrationAnchor(
                new PixelCoordinate(0.0, 1000.0),
                new InternalCoordinate(1.0, 0.0),
            ),
        ];

        $internal = $this->transformer->toInternal(new PixelCoordinate(0.0, 500.0), $anchors);

        $this->assertEqualsWithDelta(0.5, $internal->x, 1e-9);
        $this->assertEqualsWithDelta(0.0, $internal->y, 1e-9);
    }
}
