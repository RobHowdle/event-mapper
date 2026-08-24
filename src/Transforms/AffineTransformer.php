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
     * Convert festival-image pixels to geographic coordinates.
     *
     * Fits a full affine transform across all calibration anchors:
     *
     * longitude = a*x + b*y + c
     * latitude  = d*x + e*y + f
     *
     * @param CalibrationAnchor[] $anchors
     */
    public function toGeo(
        PixelCoordinate $pixel,
        array $anchors
    ): GeoCoordinate {
        $transform = $this->fitPixelToGeo($anchors);

        $longitude =
            ($transform['a'] * $pixel->x)
            + ($transform['b'] * $pixel->y)
            + $transform['c'];

        $latitude =
            ($transform['d'] * $pixel->x)
            + ($transform['e'] * $pixel->y)
            + $transform['f'];

        return new GeoCoordinate(
            latitude: $latitude,
            longitude: $longitude,
        );
    }

    /**
     * Convert geographic coordinates back to festival-image pixels.
     *
     * @param CalibrationAnchor[] $anchors
     */
    public function toPixel(
        GeoCoordinate $geo,
        array $anchors
    ): PixelCoordinate {
        $transform = $this->fitPixelToGeo($anchors);

        $a = $transform['a'];
        $b = $transform['b'];
        $c = $transform['c'];

        $d = $transform['d'];
        $e = $transform['e'];
        $f = $transform['f'];

        /*
         * Invert:
         *
         * [ longitude - c ]   [ a  b ] [ x ]
         * [ latitude  - f ] = [ d  e ] [ y ]
         */
        $determinant = ($a * $e) - ($b * $d);

        if (abs($determinant) < 1e-15) {
            throw new InvalidArgumentException(
                'Calibration transform cannot be inverted.'
            );
        }

        $longitude = $geo->longitude - $c;
        $latitude = $geo->latitude - $f;

        $x =
            (($e * $longitude) - ($b * $latitude))
            / $determinant;

        $y =
            ((-$d * $longitude) + ($a * $latitude))
            / $determinant;

        return new PixelCoordinate($x, $y);
    }

    /**
     * Fit a least-squares full affine transform using every calibration anchor.
     *
     * longitude = a*x + b*y + c
     * latitude  = d*x + e*y + f
     *
     * @param CalibrationAnchor[] $anchors
     *
     * @return array{
     *     a: float,
     *     b: float,
     *     c: float,
     *     d: float,
     *     e: float,
     *     f: float
     * }
     */
    private function fitPixelToGeo(array $anchors): array
    {
        $this->requireAtLeastThreeAnchors($anchors);

        $count = count($anchors);

        $meanX = 0.0;
        $meanY = 0.0;
        $meanLongitude = 0.0;
        $meanLatitude = 0.0;

        foreach ($anchors as $anchor) {
            $meanX += $anchor->pixel->x;
            $meanY += $anchor->pixel->y;

            $meanLongitude += $anchor->geo->longitude;
            $meanLatitude += $anchor->geo->latitude;
        }

        $meanX /= $count;
        $meanY /= $count;

        $meanLongitude /= $count;
        $meanLatitude /= $count;

        /*
         * Pixel covariance matrix:
         *
         * [ Sxx Sxy ]
         * [ Sxy Syy ]
         *
         * This lets us solve the X/Y coefficients independently
         * for longitude and latitude.
         */
        $sxx = 0.0;
        $sxy = 0.0;
        $syy = 0.0;

        $sxLongitude = 0.0;
        $syLongitude = 0.0;

        $sxLatitude = 0.0;
        $syLatitude = 0.0;

        foreach ($anchors as $anchor) {
            $x = $anchor->pixel->x - $meanX;
            $y = $anchor->pixel->y - $meanY;

            $longitude =
                $anchor->geo->longitude - $meanLongitude;

            $latitude =
                $anchor->geo->latitude - $meanLatitude;

            $sxx += $x * $x;
            $sxy += $x * $y;
            $syy += $y * $y;

            $sxLongitude += $x * $longitude;
            $syLongitude += $y * $longitude;

            $sxLatitude += $x * $latitude;
            $syLatitude += $y * $latitude;
        }

        $determinant =
            ($sxx * $syy)
            - ($sxy * $sxy);

        /*
         * A zero determinant means the calibration points are
         * effectively on one line, so a two-dimensional affine
         * transform cannot be determined.
         */
        if (abs($determinant) < 1e-10) {
            throw new InvalidArgumentException(
                'Calibration anchors must contain at least three non-collinear points.'
            );
        }

        /*
         * Longitude:
         *
         * longitude = a*x + b*y + c
         */
        $a =
            (($sxLongitude * $syy) - ($syLongitude * $sxy))
            / $determinant;

        $b =
            (($syLongitude * $sxx) - ($sxLongitude * $sxy))
            / $determinant;

        $c =
            $meanLongitude
            - ($a * $meanX)
            - ($b * $meanY);

        /*
         * Latitude:
         *
         * latitude = d*x + e*y + f
         */
        $d =
            (($sxLatitude * $syy) - ($syLatitude * $sxy))
            / $determinant;

        $e =
            (($syLatitude * $sxx) - ($sxLatitude * $sxy))
            / $determinant;

        $f =
            $meanLatitude
            - ($d * $meanX)
            - ($e * $meanY);

        return compact(
            'a',
            'b',
            'c',
            'd',
            'e',
            'f',
        );
    }

    /**
     * @param CalibrationAnchor[] $anchors
     */
    private function requireAtLeastThreeAnchors(array $anchors): void
    {
        if (count($anchors) < 3) {
            throw new InvalidArgumentException(
                'At least three calibration anchors are required for an affine transform.'
            );
        }
    }
}
