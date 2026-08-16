<?php

namespace FestivalMapper\ValueObjects;

final readonly class GeoCoordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (float) $data['latitude'],
            (float) $data['longitude'],
        );
    }
}
