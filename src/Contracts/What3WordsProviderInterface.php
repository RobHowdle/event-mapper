<?php

namespace FestivalMapper\Contracts;

use FestivalMapper\ValueObjects\GeoCoordinate;

interface What3WordsProviderInterface
{
    public function getAddress(GeoCoordinate $coordinate): ?string;
}
