<?php

namespace FestivalMapper\Engines;

use FestivalMapper\Contracts\CoordinateTransformerInterface;
use FestivalMapper\Models\CalibrationPoint;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;

class CoordinateEngine
{
    public function __construct(
        private readonly CoordinateTransformerInterface $transformer,
    ) {}

    public function pixelToGeo(
        Festival $festival,
        PixelCoordinate $pixel
    ): GeoCoordinate {
        $anchors = $this->loadAnchors($festival);

        return $this->transformer->toGeo($pixel, $anchors);
    }

    public function geoToPixel(
        Festival $festival,
        GeoCoordinate $geo
    ): PixelCoordinate {
        $anchors = $this->loadAnchors($festival);

        return $this->transformer->toPixel($geo, $anchors);
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
