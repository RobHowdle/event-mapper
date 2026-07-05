<?php

namespace FestivalMapper\Http\Controllers;

use FestivalMapper\Models\Festival;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FestivalController extends Controller
{
    public function index(): JsonResponse
    {
        $festivals = Festival::orderBy('year', 'desc')->orderBy('name')->get();

        return response()->json($festivals);
    }

    public function show(Festival $festival): JsonResponse
    {
        $festival->load(['calibrationPoints', 'mapLayers', 'pins']);

        return response()->json($festival);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'year'        => ['required', 'integer', 'min:1900', 'max:2100'],
            'description' => ['nullable', 'string'],
        ]);

        $festival = Festival::create($validated);

        return response()->json($festival, 201);
    }

    public function update(Request $request, Festival $festival): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'year'        => ['sometimes', 'integer', 'min:1900', 'max:2100'],
            'description' => ['nullable', 'string'],
        ]);

        $festival->update($validated);

        return response()->json($festival);
    }

    public function uploadMap(Request $request, Festival $festival): JsonResponse
    {
        $request->validate([
            'map_image' => ['required', 'image', 'max:20480'],
        ]);

        $path = $request->file('map_image')->store(
            'festival-mapper/maps',
            config('festival-mapper.disk', 'public')
        );

        [$width, $height] = getimagesize(
            Storage::disk(config('festival-mapper.disk', 'public'))->path($path)
        );

        $festival->update([
            'map_image_path' => $path,
            'map_width'      => $width,
            'map_height'     => $height,
        ]);

        return response()->json($festival);
    }

    public function destroy(Festival $festival): JsonResponse
    {
        $festival->delete();

        return response()->json(null, 204);
    }
}
