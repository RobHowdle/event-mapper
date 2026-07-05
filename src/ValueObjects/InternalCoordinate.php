<?php

namespace FestivalMapper\ValueObjects;

/**
 * The single source of truth coordinate used by every layer.
 *
 * The values are normalised floats in the range [0, 1] representing a
 * position relative to the festival map image (0,0 = top-left, 1,1 = bottom-right).
 * Using a normalised space keeps the coordinate system image-resolution-independent.
 */
final readonly class InternalCoordinate
{
    public function __construct(
        public float $x,
        public float $y,
    ) {}

    /**
     * @return array{x: float, y: float}
     */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }

    public static function fromArray(array $data): self
    {
        return new self((float) $data['x'], (float) $data['y']);
    }
}
