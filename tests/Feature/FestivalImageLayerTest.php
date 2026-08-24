<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Layers\FestivalImageLayer;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class FestivalImageLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    public function test_festival_image_layer_has_expected_identity_and_render_configuration(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $layer = new FestivalImageLayer(
            app(CoordinateEngine::class),
            $festival,
        );

        $this->assertSame('festival-image', $layer->id());
        $this->assertSame('Festival Map', $layer->name());

        $this->assertSame([
            'component' => 'FestivalImageMapLayer',
        ], $layer->render());
    }

    public function test_festival_image_layer_returns_map_data_and_pixel_position(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
            'map_image_path' => 'festival-mapper/maps/test.jpg',
            'map_width' => 1200,
            'map_height' => 800,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 0,
            'pixel_y' => 0,
            'latitude' => 54.5,
            'longitude' => -1.7,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 1200,
            'pixel_y' => 0,
            'latitude' => 54.5,
            'longitude' => -1.5,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 0,
            'pixel_y' => 800,
            'latitude' => 54.7,
            'longitude' => -1.7,
        ]);

        $layer = new FestivalImageLayer(
            app(CoordinateEngine::class),
            $festival,
        );

        $coordinate = new GeoCoordinate(
            54.6,
            -1.6,
        );

        $data = $layer->getData(
            $festival,
            $coordinate
        );

        $this->assertSame(
            'festival-mapper/maps/test.jpg',
            $data['image_url']
        );

        $this->assertSame(
            1200,
            $data['width']
        );

        $this->assertSame(
            800,
            $data['height']
        );

        $this->assertArrayHasKey(
            'pin_pixel',
            $data
        );

        $this->assertArrayHasKey(
            'x',
            $data['pin_pixel']
        );

        $this->assertArrayHasKey(
            'y',
            $data['pin_pixel']
        );

        $this->assertEqualsWithDelta(
            600,
            $data['pin_pixel']['x'],
            0.01
        );

        $this->assertEqualsWithDelta(
            400,
            $data['pin_pixel']['y'],
            0.01
        );
    }
}
