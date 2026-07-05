<?php

namespace FestivalMapper\Http\Controllers;

use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\Engines\PinEngine;
use FestivalMapper\Models\Festival;
use FestivalMapper\Models\Pin;
use FestivalMapper\ValueObjects\InternalCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PinController extends Controller
{
    public function __construct(
        private readonly PinEngine $pinEngine,
        private readonly CoordinateEngine $coordinateEngine,
    ) {}

    public function index(Festival $festival): JsonResponse
    {
        $pins = $this->pinEngine->pinsForFestival($festival);

        return response()->json($pins);
    }

    public function store(Request $request, Festival $festival): JsonResponse
    {
        $validated = $request->validate([
            'pixel_x'  => ['required_without:internal_x', 'nullable', 'numeric'],
            'pixel_y'  => ['required_without:internal_y', 'nullable', 'numeric'],
            'internal_x' => ['required_without:pixel_x', 'nullable', 'numeric'],
            'internal_y' => ['required_without:pixel_y', 'nullable', 'numeric'],
            'label'    => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (isset($validated['pixel_x'], $validated['pixel_y'])) {
            $coordinate = $this->coordinateEngine->pixelToInternal(
                $festival,
                new PixelCoordinate((float) $validated['pixel_x'], (float) $validated['pixel_y'])
            );
        } else {
            $coordinate = new InternalCoordinate(
                (float) $validated['internal_x'],
                (float) $validated['internal_y']
            );
        }

        $pin = $this->pinEngine->createPin(
            $festival,
            $coordinate,
            $validated['label'] ?? '',
            $validated['metadata'] ?? [],
        );

        return response()->json($pin, 201);
    }

    public function update(Request $request, Festival $festival, Pin $pin): JsonResponse
    {
        $validated = $request->validate([
            'pixel_x'    => ['sometimes', 'nullable', 'numeric'],
            'pixel_y'    => ['sometimes', 'nullable', 'numeric'],
            'internal_x' => ['sometimes', 'nullable', 'numeric'],
            'internal_y' => ['sometimes', 'nullable', 'numeric'],
            'label'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata'   => ['sometimes', 'nullable', 'array'],
        ]);

        if (isset($validated['pixel_x'], $validated['pixel_y'])) {
            $coordinate = $this->coordinateEngine->pixelToInternal(
                $festival,
                new PixelCoordinate((float) $validated['pixel_x'], (float) $validated['pixel_y'])
            );
            $pin = $this->pinEngine->movePin($pin, $coordinate);
        } elseif (isset($validated['internal_x'], $validated['internal_y'])) {
            $coordinate = new InternalCoordinate(
                (float) $validated['internal_x'],
                (float) $validated['internal_y']
            );
            $pin = $this->pinEngine->movePin($pin, $coordinate);
        }

        if (isset($validated['label'])) {
            $pin->update(['label' => $validated['label']]);
        }

        if (isset($validated['metadata'])) {
            $pin->update(['metadata' => $validated['metadata']]);
        }

        return response()->json($pin->refresh());
    }

    public function destroy(Festival $festival, Pin $pin): JsonResponse
    {
        $this->pinEngine->deletePin($pin);

        return response()->json(null, 204);
    }
}
