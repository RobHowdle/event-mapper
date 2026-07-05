<?php

namespace FestivalMapper\Contracts;

use FestivalMapper\ValueObjects\InternalCoordinate;

/**
 * A map layer plugin.
 *
 * Every layer receives the same InternalCoordinate and is responsible for
 * rendering or translating it into whatever representation it needs.
 */
interface LayerInterface
{
    /**
     * A unique machine-readable identifier for this layer.
     */
    public function id(): string;

    /**
     * Human-readable display name.
     */
    public function name(): string;

    /**
     * Return the data payload this layer needs to render the given coordinate.
     *
     * The shape of the returned array is layer-specific and consumed by the
     * corresponding Vue component on the frontend.
     *
     * @return array<string, mixed>
     */
    public function getData(InternalCoordinate $coordinate): array;

    /**
     * Return Vue component configuration needed to render this layer.
     *
     * @return array<string, mixed>
     */
    public function render(): array;
}
