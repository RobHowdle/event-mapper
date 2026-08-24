<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use FestivalMapper\Tests\TestCase;

class FestivalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    public function test_index_returns_festivals_ordered_by_year_then_name(): void
    {
        Festival::create([
            'name' => 'Older Festival',
            'year' => 2025,
        ]);

        Festival::create([
            'name' => 'Zulu Festival',
            'year' => 2026,
        ]);

        Festival::create([
            'name' => 'Alpha Festival',
            'year' => 2026,
        ]);

        $response = $this->getJson(
            '/api/festival-mapper/festivals'
        );

        $response
            ->assertOk()
            ->assertJsonPath('0.name', 'Alpha Festival')
            ->assertJsonPath('1.name', 'Zulu Festival')
            ->assertJsonPath('2.name', 'Older Festival');
    }

    public function test_store_creates_festival(): void
    {
        $response = $this->postJson(
            '/api/festival-mapper/festivals',
            [
                'name' => 'Download Festival',
                'year' => 2026,
                'description' => 'Test festival description',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('name', 'Download Festival')
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('description', 'Test festival description');

        $this->assertDatabaseHas('festival_mapper_festivals', [
            'name' => 'Download Festival',
            'year' => 2026,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson(
            '/api/festival-mapper/festivals',
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'year',
            ]);
    }

    public function test_store_validates_year_range(): void
    {
        $response = $this->postJson(
            '/api/festival-mapper/festivals',
            [
                'name' => 'Test Festival',
                'year' => 1800,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_show_returns_festival_with_relationships(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 100,
            'pixel_y' => 200,
            'latitude' => 54.6,
            'longitude' => -1.6,
            'label' => 'Main Stage',
        ]);

        $festival->mapLayers()->create([
            'layer_key' => 'test-layer',
            'name' => 'Test Layer',
        ]);

        $festival->pins()->create([
            'latitude' => 54.6,
            'longitude' => -1.6,
            'label' => 'Entrance',
        ]);

        $response = $this->getJson(
            "/api/festival-mapper/festivals/{$festival->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('name', 'Test Festival')
            ->assertJsonCount(1, 'calibration_points')
            ->assertJsonCount(1, 'map_layers')
            ->assertJsonCount(1, 'pins');
    }

    public function test_update_modifies_festival(): void
    {
        $festival = Festival::create([
            'name' => 'Old Name',
            'year' => 2026,
        ]);

        $response = $this->patchJson(
            "/api/festival-mapper/festivals/{$festival->id}",
            [
                'name' => 'New Name',
                'description' => 'Updated description',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('description', 'Updated description');

        $this->assertDatabaseHas('festival_mapper_festivals', [
            'id' => $festival->id,
            'name' => 'New Name',
        ]);
    }

    public function test_destroy_deletes_festival(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $response = $this->deleteJson(
            "/api/festival-mapper/festivals/{$festival->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing(
            'festival_mapper_festivals',
            ['id' => $festival->id]
        );
    }

    public function test_destroy_cascades_related_records(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $calibration = $festival->calibrationPoints()->create([
            'pixel_x' => 100,
            'pixel_y' => 200,
            'latitude' => 54.6,
            'longitude' => -1.6,
        ]);

        $layer = $festival->mapLayers()->create([
            'layer_key' => 'test-layer',
            'name' => 'Test Layer',
        ]);

        $pin = $festival->pins()->create([
            'latitude' => 54.6,
            'longitude' => -1.6,
        ]);

        $this->deleteJson(
            "/api/festival-mapper/festivals/{$festival->id}"
        )->assertNoContent();

        $this->assertDatabaseMissing(
            'festival_mapper_calibration_points',
            ['id' => $calibration->id]
        );

        $this->assertDatabaseMissing(
            'festival_mapper_map_layers',
            ['id' => $layer->id]
        );

        $this->assertDatabaseMissing(
            'festival_mapper_pins',
            ['id' => $pin->id]
        );
    }

    public function test_upload_map_stores_image_and_dimensions(): void
    {
        Storage::fake('public');

        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $image = UploadedFile::fake()->image(
            'festival-map.jpg',
            1200,
            800
        );

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/map",
            [
                'map_image' => $image,
            ]
        );

        $response->assertOk();

        $festival->refresh();

        $this->assertNotNull($festival->map_image_path);
        $this->assertSame(1200, $festival->map_width);
        $this->assertSame(800, $festival->map_height);

        Storage::disk('public')->assertExists(
            $festival->map_image_path
        );
    }

    public function test_upload_map_requires_an_image(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/map",
            []
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['map_image']);
    }
}
