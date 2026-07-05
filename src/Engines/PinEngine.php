<?php

namespace FestivalMapper\Engines;

use FestivalMapper\Models\Festival;
use FestivalMapper\Models\Pin;
use FestivalMapper\ValueObjects\InternalCoordinate;
use Illuminate\Database\Eloquent\Collection;

/**
 * CRUD operations for pins.
 *
 * Pins are stored against internal coordinates so they are automatically
 * synchronised across all layers.
 */
class PinEngine
{
    /**
     * @param  array<string, mixed>  $metadata  Optional freeform metadata attached to the pin.
     */
    public function createPin(
        Festival $festival,
        InternalCoordinate $coordinate,
        string $label = '',
        array $metadata = [],
    ): Pin {
        return $festival->pins()->create([
            'internal_x' => $coordinate->x,
            'internal_y' => $coordinate->y,
            'label'      => $label,
            'metadata'   => $metadata,
        ]);
    }

    public function movePin(Pin $pin, InternalCoordinate $coordinate): Pin
    {
        $pin->update([
            'internal_x' => $coordinate->x,
            'internal_y' => $coordinate->y,
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
