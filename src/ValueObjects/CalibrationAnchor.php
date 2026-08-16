<?php

namespace FestivalMapper\ValueObjects;

final readonly class CalibrationAnchor
{
    public function __construct(
        public PixelCoordinate $pixel,
        public GeoCoordinate $geo,
    ) {}

    /**
     * @return array{
     *     pixel: array{x: float, y: float},
     *     geo: array{latitude: float, longitude: float}
     * }
     */
    public function toArray(): array
    {
        return [
            'pixel' => $this->pixel->toArray(),
            'geo' => $this->geo->toArray(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            PixelCoordinate::fromArray($data['pixel']),
            GeoCoordinate::fromArray($data['geo']),
        );
    }
}
