<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;

class GeoMapLayer implements LayerInterface
{
    public function id(): string
    {
        return 'geo-map';
    }

    public function name(): string
    {
        return 'Map';
    }

    public function render(): array
    {
        return [
            'component' => 'GeoMapLayer',
        ];
    }

    public function getData(
        Festival $festival,
        GeoCoordinate $coordinate
    ): array {
        return [
            'latitude' => $coordinate->latitude,
            'longitude' => $coordinate->longitude,
        ];
    }
}
