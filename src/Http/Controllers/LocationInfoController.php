<?php

namespace FestivalMapper\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use FestivalMapper\Contracts\What3WordsProvider;
use FestivalMapper\Services\ElevationService;
use Throwable;

class LocationInfoController extends Controller
{
    public function __construct(private readonly What3WordsProvider $words, private readonly ElevationService $elevation) {}

    public function settings(): JsonResponse
    {
        try { $enabled = $this->words->enabled(); } catch (Throwable) { $enabled = false; }
        return response()->json([
            'what3words_enabled' => $enabled,
            'elevation_enabled' => (bool) config('festival-mapper.elevation.enabled', true),
            'topography_tiles' => config('festival-mapper.topography.tiles'),
            'topography_max_zoom' => config('festival-mapper.topography.max_zoom', 17),
        ]);
    }

    public function point(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $result = ['what3words' => null, 'elevation' => null, 'errors' => []];
        try {
            $result['what3words'] = $this->words->address((float) $data['latitude'], (float) $data['longitude']);
        } catch (Throwable) {
            $result['errors']['what3words'] = 'Three-word addresses are temporarily unavailable.';
        }
        if (config('festival-mapper.elevation.enabled', true)) {
            try {
                $result['elevation'] = $this->elevation->point((float) $data['latitude'], (float) $data['longitude']);
            } catch (Throwable) {
                $result['errors']['elevation'] = 'Elevation is currently unavailable for this point. Try again shortly.';
            }
        }
        return response()->json($result);
    }

    public function grid(Request $request): JsonResponse
    {
        $data = $request->validate([
            'south' => ['required', 'numeric', 'between:-90,90'],
            'north' => ['required', 'numeric', 'between:-90,90', 'gt:south'],
            'west' => ['required', 'numeric', 'between:-180,180'],
            'east' => ['required', 'numeric', 'between:-180,180', 'gt:west'],
        ]);
        $dy = deg2rad($data['north'] - $data['south']);
        $dx = deg2rad($data['east'] - $data['west']);
        $a = sin($dy / 2) ** 2 + cos(deg2rad($data['south'])) * cos(deg2rad($data['north'])) * sin($dx / 2) ** 2;
        abort_if(6371000 * 2 * asin(min(1, sqrt($a))) > 4000, 422, 'Zoom in to see the three-metre grid.');
        try {
            return response()->json($this->words->grid($data));
        } catch (Throwable) {
            return response()->json(['message' => 'The what3words grid is temporarily unavailable.'], 503);
        }
    }
}
