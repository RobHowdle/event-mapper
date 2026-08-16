<?php

namespace FestivalMapper\Engines;

use FestivalMapper\Models\Festival;
use FestivalMapper\Models\Pin;
use FestivalMapper\ValueObjects\GeoCoordinate;
use Illuminate\Database\Eloquent\Collection;

/**
 * CRUD operations for pins.
 *
 * Pins are stored using geographic coordinates so they remain independent
 * of the festival map image dimensions.
 */
class PinEngine
{
    /**
     * @param array<string, mixed> $metadata Optional freeform metadata attached to the pin.
     */
    public function createPin(
        Festival $festival,
        GeoCoordinate $coordinate,
        string $label = '',
        array $metadata = [],
    ): Pin {
        return $festival->pins()->create([
            'latitude'  => $coordinate->latitude,
            'longitude' => $coordinate->longitude,
            'label'     => $label,
            'metadata'  => $metadata,
        ]);
    }

    public function movePin(Pin $pin, GeoCoordinate $coordinate): Pin
    {
        $pin->update([
            'latitude'  => $coordinate->latitude,
            'longitude' => $coordinate->longitude,
        ]);

        return $pin->refresh();
    }

    public function deletePin(Pin $pin): void
    {
        $pin->delete();
    }

    /**
     * @return Collection<int, Pin>
     */
    public function pinsForFestival(Festival $festival): Collection
    {
        return $festival->pins()->orderBy('created_at')->get();
    }
}
