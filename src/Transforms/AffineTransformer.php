<?php

namespace FestivalMapper\Transforms;

use FestivalMapper\Contracts\CoordinateTransformerInterface;
use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\InternalCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use InvalidArgumentException;

/**
 * Simple two-anchor affine transform.
 *
 * Given two calibration anchors the transform solves for a linear mapping:
 *
 *   internal.x = a * pixel.x + b * pixel.y + c
 *   internal.y = d * pixel.x + e * pixel.y + f
 *
 * Using only two points constrains the system to a similarity transform
 * (uniform scale + rotation + translation). Three or more anchors would
 * allow a full affine solution; replace this class via the
 * CoordinateTransformerInterface binding when that is needed.
 */
class AffineTransformer implements CoordinateTransformerInterface
{
    /**
     * @param  CalibrationAnchor[]  $anchors
     */
    public function toInternal(PixelCoordinate $pixel, array $anchors): InternalCoordinate
    {
        [$a0, $a1] = $this->requireTwoAnchors($anchors);

        // Solve for scale and translation using two anchors.
        $px0 = $a0->pixel->x;
        $py0 = $a0->pixel->y;
        $px1 = $a1->pixel->x;
        $py1 = $a1->pixel->y;

        $ix0 = $a0->internal->x;
        $iy0 = $a0->internal->y;
        $ix1 = $a1->internal->x;
        $iy1 = $a1->internal->y;

        $dPx = $px1 - $px0;
        $dPy = $py1 - $py0;
        $dIx = $ix1 - $ix0;
        $dIy = $iy1 - $iy0;

        $denom = $dPx * $dPx + $dPy * $dPy;

        if (abs($denom) < 1e-10) {
            throw new InvalidArgumentException(
                'Calibration anchors are too close together to compute a reliable transform.'
            );
        }

        // Similarity transform coefficients.
        $a = ($dIx * $dPx + $dIy * $dPy) / $denom;
        $b = ($dIy * $dPx - $dIx * $dPy) / $denom;

        $c = $ix0 - $a * $px0 + $b * $py0;
        $d = $iy0 - $b * $px0 - $a * $py0;

        $x = $a * $pixel->x - $b * $pixel->y + $c;
        $y = $b * $pixel->x + $a * $pixel->y + $d;

        return new InternalCoordinate($x, $y);
    }

    /**
     * @param  CalibrationAnchor[]  $anchors
     */
    public function toPixel(InternalCoordinate $coordinate, array $anchors): PixelCoordinate
    {
        [$a0, $a1] = $this->requireTwoAnchors($anchors);

        $px0 = $a0->pixel->x;
        $py0 = $a0->pixel->y;
        $px1 = $a1->pixel->x;
        $py1 = $a1->pixel->y;

        $ix0 = $a0->internal->x;
        $iy0 = $a0->internal->y;
        $ix1 = $a1->internal->x;
        $iy1 = $a1->internal->y;

        $dPx = $px1 - $px0;
        $dPy = $py1 - $py0;
        $dIx = $ix1 - $ix0;
        $dIy = $iy1 - $iy0;

        $denom = $dIx * $dIx + $dIy * $dIy;

        if (abs($denom) < 1e-10) {
            throw new InvalidArgumentException(
                'Calibration anchors are too close together in internal space to invert the transform.'
            );
        }

        // Inverse similarity transform coefficients.
        $a = ($dPx * $dIx + $dPy * $dIy) / $denom;
        $b = ($dPy * $dIx - $dPx * $dIy) / $denom;

        $c = $px0 - $a * $ix0 + $b * $iy0;
        $d = $py0 - $b * $ix0 - $a * $iy0;

        $x = $a * $coordinate->x - $b * $coordinate->y + $c;
        $y = $b * $coordinate->x + $a * $coordinate->y + $d;

        return new PixelCoordinate($x, $y);
    }

    /**
     * @param  CalibrationAnchor[]  $anchors
     * @return array{0: CalibrationAnchor, 1: CalibrationAnchor}
     */
    private function requireTwoAnchors(array $anchors): array
    {
        if (count($anchors) < 2) {
            throw new InvalidArgumentException(
                'At least two calibration anchors are required for the affine transform.'
            );
        }

        return [$anchors[0], $anchors[1]];
    }
}
