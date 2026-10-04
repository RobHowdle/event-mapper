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
        try { $enabled = $this->words->enabled(); $message = $enabled ? null : $this->failureMessage(1000); } catch (Throwable $error) { $enabled = false; $message = $this->failureMessage($error->getCode()); }
        return response()->json([
            'what3words_enabled' => $enabled,
            'what3words_message' => $message,
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
        } catch (Throwable $error) {
            $result['errors']['what3words'] = $this->failureMessage($error->getCode());
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
        } catch (Throwable $error) {
            return response()->json(['message' => $this->failureMessage($error->getCode())], 503);
        }
    }
    private function failureMessage(int $code): string
    {
        return match ($code) {
            1000 => 'what3words has no API key configured in the location service.',
            1001 => 'The connected YN Auth service needs the what3words update.',
            1002 => 'YNF could not authenticate with YN Auth for what3words.',
            1003 => 'what3words rejected the API key configured in the location service.',
            1004 => 'The what3words API plan does not allow this request, or its quota has been reached.',
            1005 => 'what3words is receiving too many requests. Try again shortly.',
            1006 => 'YNF could not reach YN Auth for what3words.',
            default => 'Three-word addresses are temporarily unavailable.',
        };
    }

}
