<?php

namespace FestivalMapper\Tests\Unit;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Engines\LayerEngine;
use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;

class LayerEngineTest extends TestCase
{

    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    public function test_layer_can_be_registered_and_retrieved_by_id(): void
    {
        $engine = new LayerEngine();

        $layer = $this->makeLayer(
            id: 'test-layer',
            name: 'Test Layer',
        );

        $engine->register($layer);

        $this->assertSame($layer, $engine->get('test-layer'));
    }

    public function test_all_returns_registered_layers(): void
    {
        $engine = new LayerEngine();

        $first = $this->makeLayer(
            id: 'first-layer',
            name: 'First Layer',
        );

        $second = $this->makeLayer(
            id: 'second-layer',
            name: 'Second Layer',
        );

        $engine->register($first);
        $engine->register($second);

        $this->assertSame(
            [$first, $second],
            $engine->all()
        );
    }

    public function test_get_throws_for_unregistered_layer(): void
    {
        $engine = new LayerEngine();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Layer [missing-layer] is not registered.');

        $engine->get('missing-layer');
    }

    public function test_resolve_for_festival_returns_active_registered_layers(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'first-layer',
            'name' => 'First Layer',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'second-layer',
            'name' => 'Second Layer',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'inactive-layer',
            'name' => 'Inactive Layer',
            'is_active' => false,
            'sort_order' => 3,
        ]);

        $engine = new LayerEngine();

        $engine->register($this->makeLayer(
            id: 'first-layer',
            name: 'First Layer',
            data: ['value' => 'first'],
            render: ['component' => 'FirstLayer'],
        ));

        $engine->register($this->makeLayer(
            id: 'second-layer',
            name: 'Second Layer',
            data: ['value' => 'second'],
            render: ['component' => 'SecondLayer'],
        ));

        $engine->register($this->makeLayer(
            id: 'inactive-layer',
            name: 'Inactive Layer',
            data: ['value' => 'inactive'],
            render: ['component' => 'InactiveLayer'],
        ));

        $results = $engine->resolveForFestival(
            $festival,
            new GeoCoordinate(54.0, -1.0)
        );

        $this->assertCount(2, $results);

        $this->assertSame([
            'id' => 'first-layer',
            'name' => 'First Layer',
            'render' => [
                'component' => 'FirstLayer',
            ],
            'data' => [
                'value' => 'first',
            ],
        ], $results[0]);

        $this->assertSame([
            'id' => 'second-layer',
            'name' => 'Second Layer',
            'render' => [
                'component' => 'SecondLayer',
            ],
            'data' => [
                'value' => 'second',
            ],
        ], $results[1]);
    }

    public function test_resolve_for_festival_ignores_unknown_layer_keys(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'unknown-layer',
            'name' => 'Unknown Layer',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'known-layer',
            'name' => 'Known Layer',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $engine = new LayerEngine();

        $engine->register($this->makeLayer(
            id: 'known-layer',
            name: 'Known Layer',
        ));

        $results = $engine->resolveForFestival(
            $festival,
            new GeoCoordinate(54.0, -1.0)
        );

        $this->assertCount(1, $results);
        $this->assertSame('known-layer', $results[0]['id']);
    }

    public function test_resolve_for_festival_preserves_layer_sort_order(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'elevation',
            'name' => 'Elevation',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'festival-image',
            'name' => 'Festival Map',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'google',
            'name' => 'Google Maps',
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $engine = new LayerEngine();

        $engine->register($this->makeLayer(
            id: 'elevation',
            name: 'Elevation',
        ));

        $engine->register($this->makeLayer(
            id: 'festival-image',
            name: 'Festival Map',
        ));

        $engine->register($this->makeLayer(
            id: 'google',
            name: 'Google Maps',
        ));

        $results = $engine->resolveForFestival(
            $festival,
            new GeoCoordinate(54.0, -1.0)
        );

        $this->assertSame(
            [
                'festival-image',
                'google',
                'elevation',
            ],
            array_column($results, 'id')
        );
    }

    private function makeLayer(
        string $id,
        string $name,
        array $data = [],
        array $render = [],
    ): LayerInterface {
        return new class($id, $name, $data, $render) implements LayerInterface {
            public function __construct(
                private readonly string $id,
                private readonly string $name,
                private readonly array $data,
                private readonly array $render,
            ) {}

            public function id(): string
            {
                return $this->id;
            }

            public function name(): string
            {
                return $this->name;
            }

            public function getData(
                Festival $festival,
                GeoCoordinate $coordinate
            ): array {
                return $this->data;
            }

            public function render(): array
            {
                return $this->render;
            }
        };
    }
}
