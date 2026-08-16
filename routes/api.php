<?php

use FestivalMapper\Http\Controllers\CalibrationController;
use FestivalMapper\Http\Controllers\CoordinateController;
use FestivalMapper\Http\Controllers\FestivalController;
use FestivalMapper\Http\Controllers\LayerController;
use FestivalMapper\Http\Controllers\PinController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('festival-mapper.route_prefix', 'api/festival-mapper'))
    ->middleware(config('festival-mapper.middleware', ['api']))
    ->group(function () {

        // Festivals
        Route::get('festivals', [FestivalController::class, 'index']);
        Route::post('festivals', [FestivalController::class, 'store']);
        Route::get('festivals/{festival}', [FestivalController::class, 'show']);
        Route::patch('festivals/{festival}', [FestivalController::class, 'update']);
        Route::delete('festivals/{festival}', [FestivalController::class, 'destroy']);
        Route::post('festivals/{festival}/map', [FestivalController::class, 'uploadMap']);

        // Coordinates
        Route::post(
            'festivals/{festival}/coordinates/to-pixel',
            [CoordinateController::class, 'toPixel']
        );

        // Calibration points
        Route::get('festivals/{festival}/calibration', [CalibrationController::class, 'index']);
        Route::post('festivals/{festival}/calibration', [CalibrationController::class, 'store']);
        Route::patch('festivals/{festival}/calibration/{calibrationPoint}', [CalibrationController::class, 'update']);
        Route::delete('festivals/{festival}/calibration/{calibrationPoint}', [CalibrationController::class, 'destroy']);

        // Pins
        Route::get('festivals/{festival}/pins', [PinController::class, 'index']);
        Route::post('festivals/{festival}/pins', [PinController::class, 'store']);
        Route::patch('festivals/{festival}/pins/{pin}', [PinController::class, 'update']);
        Route::delete('festivals/{festival}/pins/{pin}', [PinController::class, 'destroy']);

        // Layers
        Route::get('festivals/{festival}/layers', [LayerController::class, 'index']);
        Route::post('festivals/{festival}/layers/resolve', [LayerController::class, 'resolve']);
        Route::post('festivals/{festival}/layers/{layerId}/activate', [LayerController::class, 'activate']);
        Route::post('festivals/{festival}/layers/{layerId}/deactivate', [LayerController::class, 'deactivate']);
    });
