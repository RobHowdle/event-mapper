<?php

namespace FestivalMapper\Contracts;

interface What3WordsProvider
{
    public function enabled(): bool;

    public function address(float $latitude, float $longitude): array;

    public function grid(array $bounds): array;
}
