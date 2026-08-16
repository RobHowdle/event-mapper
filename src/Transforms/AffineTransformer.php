<?php

namespace FestivalMapper\Transforms;

use FestivalMapper\Contracts\CoordinateTransformerInterface;
use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use InvalidArgumentException;

class AffineTransformer implements CoordinateTransformerInterface
{
    /**
     * Convert pixel coordinates to geographic coordinates.
     *
     * With two anchors this performs a similarity transform:
     * translation + rotation + uniform scale.
     *
     * @param CalibrationAnchor[] $anchors
     */
    public function toGeo(
        PixelCoordinate $pixel,
        array $anchors
    ): GeoCoordinate {
        [$a0, $a1] = $this->requireTwoAnchors($anchors);

        $px0 = $a0->pixel->x;
        $py0 = $a0->pixel->y;
        $px1 = $a1->pixel->x;
        $py1 = $a1->pixel->y;

        $lon0 = $a0->geo->longitude;
        $lat0 = $a0->geo->latitude;
        $lon1 = $a1->geo->longitude;
        $lat1 = $a1->geo->latitude;

        $dPx = $px1 - $px0;
        $dPy = $py1 - $py0;

        $dLon = $lon1 - $lon0;
        $dLat = $lat1 - $lat0;

        $denom = ($dPx * $dPx) + ($dPy * $dPy);

        if (abs($denom) < 1e-10) {
            throw new InvalidArgumentException(
                'Calibration anchors are too close together to compute a reliable transform.'
            );
        }

        $a = ($dLon * $dPx + $dLat * $dPy) / $denom;
        $b = ($dLat * $dPx - $dLon * $dPy) / $denom;

        $c = $lon0 - ($a * $px0) + ($b * $py0);
        $d = $lat0 - ($b * $px0) - ($a * $py0);

        $longitude = ($a * $pixel->x) - ($b * $pixel->y) + $c;
        $latitude = ($b * $pixel->x) + ($a * $pixel->y) + $d;

        return new GeoCoordinate(
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    /**
     * Convert geographic coordinates to pixel coordinates.
     *
     * @param CalibrationAnchor[] $anchors
     */
    public function toPixel(
        GeoCoordinate $geo,
        array $anchors
    ): PixelCoordinate {
        [$a0, $a1] = $this->requireTwoAnchors($anchors);

        $px0 = $a0->pixel->x;
        $py0 = $a0->pixel->y;
        $px1 = $a1->pixel->x;
        $py1 = $a1->pixel->y;

        $lon0 = $a0->geo->longitude;
        $lat0 = $a0->geo->latitude;
        $lon1 = $a1->geo->longitude;
        $lat1 = $a1->geo->latitude;

        $dPx = $px1 - $px0;
        $dPy = $py1 - $py0;

        $dLon = $lon1 - $lon0;
        $dLat = $lat1 - $lat0;

        $denom = ($dLon * $dLon) + ($dLat * $dLat);

        if (abs($denom) < 1e-10) {
            throw new InvalidArgumentException(
                'Calibration anchors are too close together in geographic space to invert the transform.'
            );
        }

        $a = ($dPx * $dLon + $dPy * $dLat) / $denom;
        $b = ($dPy * $dLon - $dPx * $dLat) / $denom;

        $c = $px0 - ($a * $lon0) + ($b * $lat0);
        $d = $py0 - ($b * $lon0) - ($a * $lat0);

        $x = ($a * $geo->longitude) - ($b * $geo->latitude) + $c;
        $y = ($b * $geo->longitude) + ($a * $geo->latitude) + $d;

        return new PixelCoordinate($x, $y);
    }

    /**
     * @param CalibrationAnchor[] $anchors
     * @return array{0: CalibrationAnchor, 1: CalibrationAnchor}
     */
    private function requireTwoAnchors(array $anchors): array
    {
        if (count($anchors) < 2) {
            throw new InvalidArgumentException(
                'At least two calibration anchors are required for the transform.'
            );
        }

        return [$anchors[0], $anchors[1]];
    }
}
