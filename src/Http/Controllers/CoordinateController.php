<?php

namespace FestivalMapper\Http\Controllers;

use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CoordinateController extends Controller
{
    public function __construct(
        private readonly CoordinateEngine $coordinateEngine,
    ) {}

    /**
     * Convert a geographic coordinate to a pixel coordinate
     * using the festival's calibration points.
     */
    public function toPixel(
        Request $request,
        Festival $festival,
    ): JsonResponse {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $geo = new GeoCoordinate(
            latitude: (float) $validated['latitude'],
            longitude: (float) $validated['longitude'],
        );

        $pixel = $this->coordinateEngine->geoToPixel(
            $festival,
            $geo,
        );

        return response()->json([
            'geo' => $geo->toArray(),
            'pixel' => $pixel->toArray(),
        ]);
    }

    /**
     * Convert a pixel coordinate to a geographic coordinate
     * using the festival's calibration points.
     */
    public function toGeo(
        Request $request,
        Festival $festival,
    ): JsonResponse {
        $validated = $request->validate([
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
        ]);

        $pixel = new PixelCoordinate(
            x: (float) $validated['x'],
            y: (float) $validated['y'],
        );

        $geo = $this->coordinateEngine->pixelToGeo(
            $festival,
            $pixel,
        );

        return response()->json([
            'pixel' => $pixel->toArray(),
            'geo' => $geo->toArray(),
        ]);
    }
}
