<?php

namespace FestivalMapper\Http\Controllers;

use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\Engines\LayerEngine;
use FestivalMapper\Models\Festival;
use FestivalMapper\Models\MapLayer;
use FestivalMapper\ValueObjects\InternalCoordinate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LayerController extends Controller
{
    public function __construct(
        private readonly LayerEngine $layerEngine,
        private readonly CoordinateEngine $coordinateEngine,
    ) {}

    /**
     * List all registered layers with their active state for the festival.
     */
    public function index(Festival $festival): JsonResponse
    {
        $activeLayers = $festival->mapLayers()->pluck('is_active', 'layer_key');

        $layers = collect($this->layerEngine->all())
            ->map(fn($layer) => [
                'id'        => $layer->id(),
                'name'      => $layer->name(),
                'is_active' => (bool) ($activeLayers[$layer->id()] ?? false),
                'render'    => $layer->render(),
            ]);

        return response()->json($layers);
    }

    /**
     * Resolve all active layers for a given internal coordinate.
     *
     * This is the primary endpoint the frontend calls when a pin is placed
     * or moved. Returns the data each layer needs to render the coordinate.
     */
    public function resolve(Request $request, Festival $festival): JsonResponse
    {
        $validated = $request->validate([
            'internal_x' => ['required', 'numeric'],
            'internal_y' => ['required', 'numeric'],
        ]);

        $coordinate = new InternalCoordinate(
            (float) $validated['internal_x'],
            (float) $validated['internal_y']
        );

        $data = $this->layerEngine->resolveForFestival($festival, $coordinate);

        return response()->json([
            'coordinate' => $coordinate->toArray(),
            'layers'     => $data,
        ]);
    }

    public function activate(Festival $festival, string $layerId): JsonResponse
    {
        $this->layerEngine->get($layerId); // Throws if not registered.

        $festival->mapLayers()->updateOrCreate(
            ['layer_key' => $layerId],
            ['is_active' => true, 'name' => $this->layerEngine->get($layerId)->name()]
        );

        return response()->json(['activated' => $layerId]);
    }

    public function deactivate(Festival $festival, string $layerId): JsonResponse
    {
        $festival->mapLayers()
            ->where('layer_key', $layerId)
            ->update(['is_active' => false]);

        return response()->json(['deactivated' => $layerId]);
    }
}
