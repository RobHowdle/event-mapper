<?php

namespace FestivalMapper\Engines;

use FestivalMapper\Contracts\CoordinateTransformerInterface;
use FestivalMapper\Models\CalibrationPoint;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\InternalCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;

/**
 * Translates between image-pixel space and the internal coordinate system
 * for a given festival.
 *
 * This engine owns no UI logic. It is the single source of truth for all
 * coordinate operations.
 */
class CoordinateEngine
{
    public function __construct(
        private readonly CoordinateTransformerInterface $transformer,
    ) {}

    /**
     * Convert a pixel click on the festival map image to an internal coordinate.
     */
    public function pixelToInternal(Festival $festival, PixelCoordinate $pixel): InternalCoordinate
    {
        $anchors = $this->loadAnchors($festival);

        return $this->transformer->toInternal($pixel, $anchors);
    }

    /**
     * Convert an internal coordinate back to a pixel position on the image.
     */
    public function internalToPixel(Festival $festival, InternalCoordinate $coordinate): PixelCoordinate
    {
        $anchors = $this->loadAnchors($festival);

        return $this->transformer->toPixel($coordinate, $anchors);
    }

    /**
     * @return CalibrationAnchor[]
     */
    private function loadAnchors(Festival $festival): array
    {
        return $festival
            ->calibrationPoints()
            ->get()
            ->map(fn(CalibrationPoint $point) => $point->toAnchor())
            ->all();
    }
}
