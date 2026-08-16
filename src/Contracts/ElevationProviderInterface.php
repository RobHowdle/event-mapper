<?php

namespace FestivalMapper\Contracts;

use FestivalMapper\ValueObjects\GeoCoordinate;

interface ElevationProviderInterface
{
    public function getElevation(GeoCoordinate $coordinate): ?float;
}
