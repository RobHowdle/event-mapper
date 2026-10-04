<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class PinConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [FestivalMapperServiceProvider::class];
    }

    public function test_location_details_are_saved_and_updated(): void
    {
        $festival = Festival::create(['name' => 'Test Festival', 'year' => 2026]);
        $path = "/api/festival-mapper/festivals/{$festival->id}/pins";
        $response = $this->postJson($path, [
            'label' => 'Medical tent', 'latitude' => 52.83, 'longitude' => -1.39,
            'metadata' => ['category' => 'medical', 'description' => 'Open all weekend', 'url' => 'https://example.com/medical'],
        ])->assertCreated()->assertJsonPath('metadata.category', 'medical');
        $this->patchJson($path.'/'.$response->json('id'), [
            'label' => 'First aid', 'metadata' => ['category' => 'medical', 'description' => 'Near the main stage'],
        ])->assertOk()->assertJsonPath('label', 'First aid')
            ->assertJsonPath('metadata.description', 'Near the main stage')
            ->assertJsonPath('latitude', 52.83);
    }

    public function test_invalid_coordinates_links_and_partial_updates_are_rejected(): void
    {
        $festival = Festival::create(['name' => 'Test Festival', 'year' => 2026]);
        $path = "/api/festival-mapper/festivals/{$festival->id}/pins";
        $this->postJson($path, ['latitude' => 100, 'longitude' => -1.39])
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->postJson($path, ['latitude' => 52.83, 'longitude' => -1.39, 'metadata' => ['url' => 'javascript:alert(1)']])
            ->assertUnprocessable()->assertJsonValidationErrors('metadata.url');
        $pin = $festival->pins()->create(['latitude' => 52.83, 'longitude' => -1.39, 'label' => 'Original']);
        $this->patchJson($path.'/'.$pin->id, ['latitude' => 53])
            ->assertUnprocessable()->assertJsonValidationErrors('longitude');
        $this->assertDatabaseHas('festival_mapper_pins', ['id' => $pin->id, 'latitude' => 52.83]);
    }

    public function test_legacy_pin_schema_preserves_data_and_accepts_new_locations(): void
    {
        $festival = Festival::create(['name' => 'Legacy Festival', 'year' => 2026]);
        Schema::drop('festival_mapper_pins');
        Schema::create('festival_mapper_pins', function (Blueprint $table) {
            $table->id(); $table->foreignId('festival_id')->constrained('festival_mapper_festivals');
            $table->double('internal_x'); $table->double('internal_y');
            $table->string('label')->default(''); $table->json('metadata')->nullable(); $table->timestamps();
        });
        DB::table('festival_mapper_pins')->insert(['festival_id' => $festival->id, 'internal_x' => 5000, 'internal_y' => 6000, 'label' => 'Legacy location']);
        $migration = require __DIR__.'/../../database/migrations/2026_10_04_000006_upgrade_pins_to_geographic_coordinates.php';
        $migration->up(); $migration->up();
        $this->assertDatabaseHas('festival_mapper_pins', ['label' => 'Legacy location', 'internal_x' => 5000, 'latitude' => null]);
        $this->postJson("/api/festival-mapper/festivals/{$festival->id}/pins", [
            'label' => 'New location', 'latitude' => 52.83, 'longitude' => -1.39,
        ])->assertCreated();
        $this->assertDatabaseHas('festival_mapper_pins', ['label' => 'New location', 'latitude' => 52.83, 'internal_x' => null]);
    }
}
