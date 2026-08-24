<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\ElevationProviderInterface;
use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\Models\Festival;

class ElevationLayer implements LayerInterface
{
    public function __construct(
        private readonly ElevationProviderInterface $provider,
    ) {}

    public function id(): string
    {
        return 'elevation';
    }

    public function name(): string
    {
        return 'Topography';
    }

    public function render(): array
    {
        return [
            'component' => 'ElevationLayer',
        ];
    }

    public function getData(Festival $festival, GeoCoordinate $coordinate): array
    {
        return [
            'elevation' => $this->provider->getElevation($coordinate),
        ];
    }
}
