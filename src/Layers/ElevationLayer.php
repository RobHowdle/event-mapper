<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\ElevationProviderInterface;
use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\ValueObjects\GeoCoordinate;

class ElevationLayer implements LayerInterface
{
    public function __construct(
        private readonly ElevationProviderInterface $provider,
    ) {}

    public function getData(GeoCoordinate $coordinate): array
    {
        return [
            'elevation' => $this->provider->getElevation($coordinate),
        ];
    }
}
