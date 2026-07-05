<?php

namespace FestivalMapper\Contracts;

use FestivalMapper\ValueObjects\InternalCoordinate;

/**
 * Retrieves elevation data for a given internal coordinate.
 *
 * Implement this interface to swap elevation providers without touching the
 * core package.
 */
interface ElevationProviderInterface
{
    /**
     * Return elevation in metres above sea level, or null when unavailable.
     */
    public function getElevation(InternalCoordinate $coordinate): ?float;
}
