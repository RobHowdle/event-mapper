<?php

namespace FestivalMapper;

use Illuminate\Support\ServiceProvider;

use FestivalMapper\Contracts\CoordinateTransformerInterface;
use FestivalMapper\Transforms\AffineTransformer;
use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\Engines\LayerEngine;
use FestivalMapper\Engines\PinEngine;
use FestivalMapper\Layers\FestivalImageLayer;
use FestivalMapper\Layers\GeoMapLayer;

class FestivalMapperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/festival-mapper.php',
            'festival-mapper'
        );

        $this->app->bind(CoordinateTransformerInterface::class, AffineTransformer::class);

        $this->app->singleton(CoordinateEngine::class);
        $this->app->singleton(LayerEngine::class);
        $this->app->singleton(PinEngine::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/festival-mapper.php' => config_path('festival-mapper.php'),
            ], 'festival-mapper-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'festival-mapper-migrations');

            $this->publishes([
                __DIR__ . '/../resources/js' => resource_path('js/vendor/festival-mapper'),
            ], 'festival-mapper-assets');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        $layerEngine = $this->app->make(LayerEngine::class);

        $layerEngine->register(
            $this->app->make(FestivalImageLayer::class)
        );

        $layerEngine->register(
            $this->app->make(GeoMapLayer::class)
        );
    }
}
