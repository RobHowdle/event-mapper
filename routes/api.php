<?php

use FestivalMapper\Http\Controllers\CalibrationController;
use FestivalMapper\Http\Controllers\CoordinateController;
use FestivalMapper\Http\Controllers\FestivalController;
use FestivalMapper\Http\Controllers\LayerController;
use FestivalMapper\Http\Controllers\PinController;
use FestivalMapper\Http\Controllers\LocationInfoController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('festival-mapper.route_prefix', 'api/festival-mapper'))
    ->middleware(config('festival-mapper.middleware', ['api']))
    ->group(function () {

        // Provider keys stay server-side; these endpoints expose only results.
        Route::get('location-info/settings', [LocationInfoController::class, 'settings']);
        Route::get('location-info/point', [LocationInfoController::class, 'point'])->middleware('throttle:60,1');
        Route::get('location-info/grid', [LocationInfoController::class, 'grid'])->middleware('throttle:60,1');

        // Festivals
        Route::get('festivals', [FestivalController::class, 'index']);
        Route::post('festivals', [FestivalController::class, 'store']);
        Route::get('festivals/{festival}', [FestivalController::class, 'show']);
        Route::patch('festivals/{festival}', [FestivalController::class, 'update']);
        Route::delete('festivals/{festival}', [FestivalController::class, 'destroy']);
        Route::post('festivals/{festival}/map', [FestivalController::class, 'uploadMap']);

        // Coordinates
        Route::post('festivals/{festival}/coordinates/to-pixel', [CoordinateController::class, 'toPixel']);
        Route::post('festivals/{festival}/coordinates/to-geo', [CoordinateController::class, 'toGeo']);

        Route::get('festivals/{festival}/terrain', [\FestivalMapper\Http\Controllers\TerrainController::class, 'show'])->middleware('throttle:20,1');

        // Calibration points
        Route::get('festivals/{festival}/calibration', [CalibrationController::class, 'index']);
        Route::post('festivals/{festival}/calibration', [CalibrationController::class, 'store']);
        Route::patch('festivals/{festival}/calibration/{calibrationPoint}', [CalibrationController::class, 'update'])
            ->scopeBindings();
        Route::delete('festivals/{festival}/calibration/{calibrationPoint}', [CalibrationController::class, 'destroy'])
            ->scopeBindings();

        // Pins
        Route::get('festivals/{festival}/pins', [PinController::class, 'index']);
        Route::post('festivals/{festival}/pins', [PinController::class, 'store']);
        Route::patch('festivals/{festival}/pins/{pin}', [PinController::class, 'update'])
            ->scopeBindings();
        Route::delete('festivals/{festival}/pins/{pin}', [PinController::class, 'destroy'])
            ->scopeBindings();

        // Layers
        Route::get('festivals/{festival}/layers', [LayerController::class, 'index']);
        Route::post('festivals/{festival}/layers/resolve', [LayerController::class, 'resolve']);
        Route::post('festivals/{festival}/layers/{layerId}/activate', [LayerController::class, 'activate']);
        Route::post('festivals/{festival}/layers/{layerId}/deactivate', [LayerController::class, 'deactivate']);
    });

