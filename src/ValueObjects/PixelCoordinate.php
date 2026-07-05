<?php

namespace FestivalMapper\ValueObjects;

/**
 * A raw pixel position on the festival map image.
 */
final readonly class PixelCoordinate
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
