<?php

namespace FestivalMapper\Contracts;

use FestivalMapper\ValueObjects\InternalCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use FestivalMapper\ValueObjects\CalibrationAnchor;

/**
 * Transforms between pixel (image) space and the internal coordinate system.
 *
 * Implementations should be stateless. The calibration anchors supply all
 * the context needed to perform the transform.
 */
interface CoordinateTransformerInterface
{
    /**
     * Convert a pixel location on the map image to an internal coordinate.
     *
     * @param  PixelCoordinate      $pixel
     * @param  CalibrationAnchor[]  $anchors  At least two anchors are required.
     * @return InternalCoordinate
     */
    public function toInternal(PixelCoordinate $pixel, array $anchors): InternalCoordinate;

    /**
     * Convert an internal coordinate back to a pixel location on the map image.
     *
     * @param  InternalCoordinate   $coordinate
     * @param  CalibrationAnchor[]  $anchors
     * @return PixelCoordinate
     */
    public function toPixel(InternalCoordinate $coordinate, array $anchors): PixelCoordinate;
}
