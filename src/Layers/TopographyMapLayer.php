<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;

class TopographyMapLayer implements LayerInterface
{
    public function id(): string
    {
        return 'topography';
    }

    public function name(): string
    {
        return 'Topography';
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

