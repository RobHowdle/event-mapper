<?php

namespace FestivalMapper\Tests\Unit;

use FestivalMapper\Contracts\ElevationProviderInterface;
use FestivalMapper\Contracts\What3WordsProviderInterface;
use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\Layers\ElevationLayer;
use FestivalMapper\Layers\FestivalImageLayer;
use FestivalMapper\Layers\What3WordsLayer;
use FestivalMapper\Models\Festival;
use FestivalMapper\Tests\TestCase;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LayersTest extends TestCase
{
    use RefreshDatabase;
    public function test_elevation_layer_has_expected_identity_and_render_configuration(): void
    {
        $provider = Mockery::mock(ElevationProviderInterface::class);

        $layer = new ElevationLayer($provider);

        $this->assertSame('elevation', $layer->id());
        $this->assertSame('Topography', $layer->name());
        $this->assertSame([
            'component' => 'ElevationLayer',
        ], $layer->render());
    }

    public function test_elevation_layer_returns_provider_data(): void
    {
        $festival = new Festival([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $coordinate = new GeoCoordinate(54.6, -1.6);

        $provider = Mockery::mock(ElevationProviderInterface::class);

        $provider
            ->shouldReceive('getElevation')
            ->once()
            ->with($coordinate)
            ->andReturn(142.5);

        $layer = new ElevationLayer($provider);

        $this->assertSame([
            'elevation' => 142.5,
        ], $layer->getData($festival, $coordinate));
    }

    public function test_what3words_layer_has_expected_identity_and_render_configuration(): void
    {
        $provider = Mockery::mock(What3WordsProviderInterface::class);

        $layer = new What3WordsLayer($provider);

        $this->assertSame('what3words', $layer->id());
        $this->assertSame('What3Words', $layer->name());
        $this->assertSame([
            'component' => 'What3WordsLayer',
        ], $layer->render());
    }

    public function test_what3words_layer_returns_provider_data(): void
    {
        $festival = new Festival([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $coordinate = new GeoCoordinate(54.6, -1.6);

        $provider = Mockery::mock(What3WordsProviderInterface::class);

        $provider
            ->shouldReceive('getAddress')
            ->once()
            ->with($coordinate)
            ->andReturn('filled.count.soap');

        $layer = new What3WordsLayer($provider);

        $this->assertSame([
            'address' => 'filled.count.soap',
        ], $layer->getData($festival, $coordinate));
    }

    public function test_festival_image_layer_has_expected_identity_and_render_configuration(): void
    {
        $festival = new Festival([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $coordinateEngine = Mockery::mock(CoordinateEngine::class);

        $layer = new FestivalImageLayer(
            $coordinateEngine,
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
        $festival = new Festival([
            'name' => 'Test Festival',
            'year' => 2026,
            'map_image_path' => 'festival-mapper/maps/test.jpg',
            'map_width' => 1200,
            'map_height' => 800,
        ]);

        $coordinate = new GeoCoordinate(54.6, -1.6);

        $pixel = new PixelCoordinate(450, 275);

        $coordinateEngine = Mockery::mock(CoordinateEngine::class);

        $coordinateEngine
            ->shouldReceive('geoToPixel')
            ->once()
            ->with($festival, $coordinate)
            ->andReturn($pixel);

        $layer = new FestivalImageLayer(
            $coordinateEngine,
            $festival,
        );

        $this->assertSame([
            'image_url' => 'festival-mapper/maps/test.jpg',
            'width' => 1200,
            'height' => 800,
            'pin_pixel' => [
                'x' => 450.0,
                'y' => 275.0,
            ],
        ], $layer->getData($festival, $coordinate));
    }
}
