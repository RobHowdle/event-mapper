<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Contracts\What3WordsProviderInterface;
use FestivalMapper\ValueObjects\GeoCoordinate;

class What3WordsLayer implements LayerInterface
{
    public function __construct(
        private readonly What3WordsProviderInterface $provider,
    ) {}

    public function getData(GeoCoordinate $coordinate): array
    {
        return [
            'address' => $this->provider->getAddress($coordinate),
        ];
    }
}
