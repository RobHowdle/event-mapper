<?php

namespace FestivalMapper\Contracts;

use FestivalMapper\ValueObjects\InternalCoordinate;

/**
 * Converts an internal coordinate to a What3Words address.
 *
 * Implement this interface to swap What3Words providers without touching the
 * core package.
 */
interface What3WordsProviderInterface
{
    /**
     * Return the What3Words address (e.g. "filled.count.soap") for the given
     * internal coordinate, or null when the address cannot be resolved.
     */
    public function getAddress(InternalCoordinate $coordinate): ?string;
}
