<?php

namespace FestivalMapper\Http\Controllers;

use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\Models\Festival;
use FestivalMapper\Services\ElevationService;
use FestivalMapper\ValueObjects\PixelCoordinate;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class TerrainController extends Controller
{
    public function show(Festival $festival, CoordinateEngine $coordinates, ElevationService $elevation): JsonResponse
    {
        if (! config('festival-mapper.elevation.enabled', true)) {
            return response()->json(['message' => 'Elevation is disabled for this site.'], 503);
        }
        if (! $festival->map_width || ! $festival->map_height) {
            return response()->json(['message' => 'Upload and calibrate the festival map to show elevation.'], 422);
        }
        try {
            $corners = array_map(fn ($point) => $coordinates->pixelToGeo($festival, new PixelCoordinate($point[0], $point[1])), [
                [0, 0], [$festival->map_width, 0], [0, $festival->map_height], [$festival->map_width, $festival->map_height],
            ]);
            $latitudes = array_map(fn ($point) => $point->latitude, $corners);
            $longitudes = array_map(fn ($point) => $point->longitude, $corners);
            $bounds = ['south' => min($latitudes), 'north' => max($latitudes), 'west' => min($longitudes), 'east' => max($longitudes)];
            $height = ($bounds['north'] - $bounds['south']) * 111320;
            $width = ($bounds['east'] - $bounds['west']) * 111320 * cos(deg2rad(($bounds['south'] + $bounds['north']) / 2));
            if ($bounds['south'] < -85 || $bounds['north'] > 85 || $bounds['west'] < -180 || $bounds['east'] > 180
                || $height <= 0 || $width <= 0 || hypot($height, $width) > 20000) {
                return response()->json(['message' => 'Check map calibration before loading the elevation layer.'], 422);
            }
        } catch (Throwable) {
            return response()->json(['message' => 'Elevation needs at least three valid calibration points.'], 422);
        }

        $size = 10;
        $points = [];
        for ($row = 0; $row < $size; $row++) {
            for ($column = 0; $column < $size; $column++) {
                $points[] = [
                    'latitude' => round($bounds['north'] - ($bounds['north'] - $bounds['south']) * $row / ($size - 1), 7),
                    'longitude' => round($bounds['west'] + ($bounds['east'] - $bounds['west']) * $column / ($size - 1), 7),
                ];
            }
        }
        try {
            $sample = $elevation->samples($points);
            $valid = array_filter($sample['heights'], fn ($value) => $value !== null);
            if (! count($valid)) {
                return response()->json(['message' => 'No terrain elevation is available for this festival.'], 503);
            }
            return response()->json([
                'bounds' => $bounds, 'rows' => $size, 'columns' => $size, 'heights' => $sample['heights'],
                'minimum' => min($valid), 'maximum' => max($valid), 'dataset' => $sample['dataset'],
                'sample_spacing_metres' => round(max($height, $width) / ($size - 1)),
            ])->header('Cache-Control', 'private, max-age=3600');
        } catch (Throwable) {
            return response()->json(['message' => 'Terrain elevation is temporarily unavailable. Try again shortly.'], 503);
        }
    }
}
