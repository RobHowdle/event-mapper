<?php

namespace FestivalMapper\Http\Controllers;

use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\Engines\PinEngine;
use FestivalMapper\Models\Festival;
use FestivalMapper\Models\Pin;
use FestivalMapper\ValueObjects\GeoCoordinate;
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
            'pixel_x'   => ['required_with:pixel_y', 'required_without:latitude', 'nullable', 'numeric'],
            'pixel_y'   => ['required_with:pixel_x', 'required_without:longitude', 'nullable', 'numeric'],
            'latitude'  => ['required_with:longitude', 'required_without:pixel_x', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'required_without:pixel_y', 'nullable', 'numeric', 'between:-180,180'],
            'label'     => ['nullable', 'string', 'max:255'],
            'metadata'  => ['nullable', 'array'],
            'metadata.category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'metadata.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'metadata.url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
        ]);

        if (isset($validated['pixel_x'], $validated['pixel_y'])) {
            $coordinate = $this->coordinateEngine->pixelToGeo(
                $festival,
                new PixelCoordinate(
                    (float) $validated['pixel_x'],
                    (float) $validated['pixel_y'],
                ),
            );
        } else {
            $coordinate = new GeoCoordinate(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
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

    public function update(
        Request $request,
        Festival $festival,
        Pin $pin,
    ): JsonResponse {
        $validated = $request->validate([
            'pixel_x'   => ['required_with:pixel_y', 'nullable', 'numeric'],
            'pixel_y'   => ['required_with:pixel_x', 'nullable', 'numeric'],
            'latitude'  => ['required_with:longitude', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_with:latitude', 'nullable', 'numeric', 'between:-180,180'],
            'label'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata'  => ['sometimes', 'nullable', 'array'],
        ]);

        if (isset($validated['pixel_x'], $validated['pixel_y'])) {
            $coordinate = $this->coordinateEngine->pixelToGeo(
                $festival,
                new PixelCoordinate(
                    (float) $validated['pixel_x'],
                    (float) $validated['pixel_y'],
                ),
            );

            $pin = $this->pinEngine->movePin($pin, $coordinate);
        } elseif (isset($validated['latitude'], $validated['longitude'])) {
            $coordinate = new GeoCoordinate(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
            );

            $pin = $this->pinEngine->movePin($pin, $coordinate);
        }

        if (isset($validated['label'])) {
            $pin->update([
                'label' => $validated['label'],
            ]);
        }

        if (isset($validated['metadata'])) {
            $pin->update([
                'metadata' => $validated['metadata'],
            ]);
        }

        return response()->json($pin->refresh());
    }

    public function destroy(Festival $festival, Pin $pin): JsonResponse
    {
        $this->pinEngine->deletePin($pin);

        return response()->json(null, 204);
    }
}

