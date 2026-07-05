<?php

namespace FestivalMapper\Http\Controllers;

use FestivalMapper\Models\CalibrationPoint;
use FestivalMapper\Models\Festival;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CalibrationController extends Controller
{
    public function index(Festival $festival): JsonResponse
    {
        return response()->json($festival->calibrationPoints()->get());
    }

    public function store(Request $request, Festival $festival): JsonResponse
    {
        $validated = $request->validate([
            'pixel_x'    => ['required', 'numeric'],
            'pixel_y'    => ['required', 'numeric'],
            'internal_x' => ['required', 'numeric'],
            'internal_y' => ['required', 'numeric'],
            'label'      => ['nullable', 'string', 'max:255'],
        ]);

        $point = $festival->calibrationPoints()->create($validated);

        return response()->json($point, 201);
    }

    public function update(Request $request, Festival $festival, CalibrationPoint $calibrationPoint): JsonResponse
    {
        $validated = $request->validate([
            'pixel_x'    => ['sometimes', 'numeric'],
            'pixel_y'    => ['sometimes', 'numeric'],
            'internal_x' => ['sometimes', 'numeric'],
            'internal_y' => ['sometimes', 'numeric'],
            'label'      => ['nullable', 'string', 'max:255'],
        ]);

        $calibrationPoint->update($validated);

        return response()->json($calibrationPoint);
    }

    public function destroy(Festival $festival, CalibrationPoint $calibrationPoint): JsonResponse
    {
        $calibrationPoint->delete();

        return response()->json(null, 204);
    }
}
