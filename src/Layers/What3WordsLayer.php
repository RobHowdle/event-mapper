<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Contracts\What3WordsProviderInterface;
use FestivalMapper\ValueObjects\InternalCoordinate;

/**
 * Resolves a What3Words address for the given internal coordinate.
 *
 * The actual API call is delegated to a What3WordsProviderInterface
 * implementation, keeping this layer decoupled from any vendor SDK.
 */
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

    public function getData(InternalCoordinate $coordinate): array
    {
        $address = $this->provider->getAddress($coordinate);

        return [
            'address'    => $address,
            'coordinate' => $coordinate->toArray(),
        ];
    }

    public function render(): array
    {
        return [
            'component' => 'What3WordsLayer',
        ];
    }
}
