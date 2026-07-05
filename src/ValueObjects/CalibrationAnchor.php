<?php

namespace FestivalMapper\ValueObjects;

/**
 * A calibration anchor links a known pixel position on the map image to a
 * known internal coordinate.
 *
 * A minimum of two anchors are needed for an affine transform.  Three or more
 * anchors will allow a least-squares best-fit in future implementations.
 */
final readonly class CalibrationAnchor
{
    public function __construct(
        public PixelCoordinate    $pixel,
        public InternalCoordinate $internal,
    ) {}

    /**
     * @return array{pixel: array{x: float, y: float}, internal: array{x: float, y: float}}
     */
    public function toArray(): array
    {
        return [
            'pixel'    => $this->pixel->toArray(),
            'internal' => $this->internal->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            PixelCoordinate::fromArray($data['pixel']),
            InternalCoordinate::fromArray($data['internal']),
        );
    }
}
