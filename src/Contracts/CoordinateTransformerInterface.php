<?php

namespace FestivalMapper\Contracts;

use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;

interface CoordinateTransformerInterface
{
    /**
     * @param CalibrationAnchor[] $anchors
     */
    public function toGeo(
        PixelCoordinate $pixel,
        array $anchors
    ): GeoCoordinate;

    /**
     * @param CalibrationAnchor[] $anchors
     */
    public function toPixel(
        GeoCoordinate $geo,
        array $anchors
    ): PixelCoordinate;
}
