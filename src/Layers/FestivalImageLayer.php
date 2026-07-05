<?php

namespace FestivalMapper\Layers;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\InternalCoordinate;

/**
 * Displays the uploaded festival map image.
 *
 * Returns the pixel position for the given internal coordinate so the
 * frontend Vue component can render the pin at the correct location.
 */
class FestivalImageLayer implements LayerInterface
{
    public function __construct(
        private readonly CoordinateEngine $coordinateEngine,
        private readonly Festival $festival,
    ) {}

    public function id(): string
    {
        return 'festival-image';
    }

    public function name(): string
    {
        return 'Festival Map';
    }

    public function getData(InternalCoordinate $coordinate): array
    {
        $pixel = $this->coordinateEngine->internalToPixel($this->festival, $coordinate);

        return [
            'image_url' => $this->festival->map_image_path,
            'width'     => $this->festival->map_width,
            'height'    => $this->festival->map_height,
            'pin_pixel' => $pixel->toArray(),
        ];
    }

    public function render(): array
    {
        return [
            'component' => 'FestivalImageLayer',
        ];
    }
}
