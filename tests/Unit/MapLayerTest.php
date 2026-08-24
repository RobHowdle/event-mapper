<?php

namespace FestivalMapper\Tests\Unit;

use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use FestivalMapper\Models\MapLayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class MapLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    public function test_map_layer_belongs_to_festival(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $layer = MapLayer::create([
            'festival_id' => $festival->id,
            'layer_key' => 'test-layer',
            'name' => 'Test Layer',
        ]);

        $this->assertTrue($layer->festival->is($festival));
    }

    public function test_map_layer_casts_attributes_correctly(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $layer = MapLayer::create([
            'festival_id' => $festival->id,
            'layer_key' => 'test-layer',
            'name' => 'Test Layer',
            'is_active' => 1,
            'sort_order' => '10',
            'options' => [
                'opacity' => 0.5,
                'interactive' => true,
            ],
        ]);

        $layer->refresh();

        $this->assertIsBool($layer->is_active);
        $this->assertTrue($layer->is_active);

        $this->assertIsInt($layer->sort_order);
        $this->assertSame(10, $layer->sort_order);

        $this->assertIsArray($layer->options);
        $this->assertSame([
            'opacity' => 0.5,
            'interactive' => true,
        ], $layer->options);
    }

    public function test_festival_can_create_and_retrieve_map_layers(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $layer = $festival->mapLayers()->create([
            'layer_key' => 'test-layer',
            'name' => 'Test Layer',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $this->assertTrue(
            $festival->mapLayers()->whereKey($layer->id)->exists()
        );

        $this->assertSame(
            'test-layer',
            $festival->mapLayers()->first()->layer_key
        );
    }

    public function test_active_layers_are_filtered_and_ordered(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'third',
            'name' => 'Third',
            'is_active' => true,
            'sort_order' => 30,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'first',
            'name' => 'First',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'inactive',
            'name' => 'Inactive',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $this->assertSame(
            ['first', 'third'],
            $festival->activeLayers()
                ->pluck('layer_key')
                ->all()
        );
    }

    public function test_layer_key_is_unique_per_festival(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'test-layer',
            'name' => 'Test Layer',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $festival->mapLayers()->create([
            'layer_key' => 'test-layer',
            'name' => 'Duplicate Layer',
        ]);
    }
}
