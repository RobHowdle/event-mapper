<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Contracts\What3WordsProviderInterface;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;

class What3WordsLayer implements LayerInterface
{
    public function __construct(
        private readonly What3WordsProviderInterface $provider,
    ) {}

    public function id(): string
    {
        return 'what3words';
    }

    public function name(): string
    {
        return 'What3Words';
    }

    public function render(): array
    {
        return [
            'component' => 'What3WordsLayer',
        ];
    }

    public function getData(Festival $festival, GeoCoordinate $coordinate): array
    {
        return [
            'address' => $this->provider->getAddress($coordinate),
        ];
    }
}
