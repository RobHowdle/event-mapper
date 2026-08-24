<?php

namespace FestivalMapper\Contracts;

use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\Models\Festival;

interface LayerInterface
{
    /**
     * Return the unique identifier for this layer.
     */
    public function id(): string;

    /**
     * Return the human-readable name for this layer.
     */
    public function name(): string;

    /**
     * Resolve layer data for a geographic coordinate.
     *
     * @return array<string, mixed>
     */
    public function getData(
        Festival $festival,
        GeoCoordinate $coordinate
    ): array;
    /**
     * Return the frontend rendering configuration for this layer.
     *
     * @return array<string, mixed>
     */
    public function render(): array;
}
