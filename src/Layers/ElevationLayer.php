<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\ElevationProviderInterface;
use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\ValueObjects\InternalCoordinate;

/**
 * Retrieves elevation data for the given internal coordinate.
 *
 * The actual data retrieval is delegated to an ElevationProviderInterface
 * implementation, allowing any backend provider to be swapped in.
 */
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
        return 'Elevation';
    }

    public function getData(InternalCoordinate $coordinate): array
    {
        $elevation = $this->provider->getElevation($coordinate);

        return [
            'elevation_metres' => $elevation,
            'coordinate'       => $coordinate->toArray(),
        ];
    }

    public function render(): array
    {
        return [
            'component' => 'ElevationLayer',
        ];
    }
}
