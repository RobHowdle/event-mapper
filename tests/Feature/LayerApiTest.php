<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\Engines\LayerEngine;
use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class LayerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    public function test_layers_endpoint_returns_registered_layers_with_active_state(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'festival-image',
            'name' => 'Festival Map',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'elevation',
            'name' => 'Elevation',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $engine = app(LayerEngine::class);

        $engine->register($this->makeLayer(
            id: 'festival-image',
            name: 'Festival Map',
            render: ['component' => 'FestivalImageMapLayer'],
        ));

        $engine->register($this->makeLayer(
            id: 'elevation',
            name: 'Elevation',
            render: ['component' => 'Elevation'],
        ));

        $response = $this->getJson(
            "/api/festival-mapper/festivals/{$festival->id}/layers"
        );

        $response
            ->assertOk()
            ->assertJson([
                [
                    'id' => 'festival-image',
                    'name' => 'Festival Map',
                    'is_active' => true,
                    'render' => [
                        'component' => 'FestivalImageMapLayer',
                    ],
                ],
                [
                    'id' => 'geo-map',
                    'name' => 'Map',
                    'is_active' => false,
                    'render' => [
                        'component' => 'GeoMapLayer',
                    ],
                ],
                [
                    'id' => 'elevation',
                    'name' => 'Elevation',
                    'is_active' => false,
                    'render' => [
                        'component' => 'Elevation',
                    ],
                ],
            ]);
    }

    public function test_activate_endpoint_activates_registered_layer(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $engine = app(LayerEngine::class);

        $engine->register($this->makeLayer(
            id: 'elevation',
            name: 'Elevation',
        ));

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/layers/elevation/activate"
        );

        $response
            ->assertOk()
            ->assertJson([
                'activated' => 'elevation',
            ]);

        $this->assertDatabaseHas('festival_mapper_map_layers', [
            'festival_id' => $festival->id,
            'layer_key' => 'elevation',
            'name' => 'Elevation',
            'is_active' => true,
        ]);
    }

    public function test_deactivate_endpoint_deactivates_layer(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'elevation',
            'name' => 'Elevation',
            'is_active' => true,
        ]);

        $engine = app(LayerEngine::class);

        $engine->register($this->makeLayer(
            id: 'elevation',
            name: 'Elevation',
        ));

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/layers/elevation/deactivate"
        );

        $response
            ->assertOk()
            ->assertJson([
                'deactivated' => 'elevation',
            ]);

        $this->assertDatabaseHas('festival_mapper_map_layers', [
            'festival_id' => $festival->id,
            'layer_key' => 'elevation',
            'is_active' => false,
        ]);
    }

    public function test_resolve_endpoint_returns_active_layer_data(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'test-layer',
            'name' => 'Test Layer',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $engine = app(LayerEngine::class);

        $engine->register($this->makeLayer(
            id: 'test-layer',
            name: 'Test Layer',
            data: [
                'value' => 'hello',
            ],
            render: [
                'component' => 'TestLayer',
            ],
        ));

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/layers/resolve",
            [
                'latitude' => 54.5,
                'longitude' => -1.5,
            ]
        );

        $response
            ->assertOk()
            ->assertJson([
                'coordinate' => [
                    'latitude' => 54.5,
                    'longitude' => -1.5,
                ],
                'layers' => [
                    [
                        'id' => 'test-layer',
                        'name' => 'Test Layer',
                        'render' => [
                            'component' => 'TestLayer',
                        ],
                        'data' => [
                            'value' => 'hello',
                        ],
                    ],
                ],
            ]);
    }

    public function test_resolve_endpoint_requires_coordinates(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/layers/resolve"
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'latitude',
            'longitude',
        ]);
    }

    public function test_activate_endpoint_rejects_unregistered_layer(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/layers/missing-layer/activate"
        );

        $response->assertStatus(500);
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
