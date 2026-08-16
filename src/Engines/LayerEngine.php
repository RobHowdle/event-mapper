<?php

namespace FestivalMapper\Engines;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;
use InvalidArgumentException;

/**
 * Manages the set of registered layer plugins and dispatches coordinates to them.
 *
 * Layers are registered at application boot time (e.g. in a service provider).
 * The engine is stateless with respect to which layer is "active"; that is
 * controlled by the frontend and the API.
 */
class LayerEngine
{
    /** @var array<string, LayerInterface> */
    private array $layers = [];

    public function register(LayerInterface $layer): void
    {
        $this->layers[$layer->id()] = $layer;
    }

    /**
     * @return LayerInterface[]
     */
    public function all(): array
    {
        return array_values($this->layers);
    }

    public function get(string $id): LayerInterface
    {
        if (! isset($this->layers[$id])) {
            throw new InvalidArgumentException("Layer [{$id}] is not registered.");
        }

        return $this->layers[$id];
    }

    /**
     * Return the render configuration and coordinate data for every layer
     * that is active on the given festival.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolveForFestival(Festival $festival, GeoCoordinate $coordinate): array
    {
        $activeLayerIds = $festival->activeLayers()->pluck('layer_key')->all();

        return collect($activeLayerIds)
            ->filter(fn(string $id) => isset($this->layers[$id]))
            ->map(function (string $id) use ($coordinate) {
                $layer = $this->layers[$id];

                return [
                    'id'     => $layer->id(),
                    'name'   => $layer->name(),
                    'render' => $layer->render(),
                    'data'   => $layer->getData($coordinate),
                ];
            })
            ->values()
            ->all();
    }
}
