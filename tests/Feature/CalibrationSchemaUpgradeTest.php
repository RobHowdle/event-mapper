<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\Contracts\CoordinateTransformerInterface;
use FestivalMapper\Engines\CoordinateEngine;
use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use FestivalMapper\ValueObjects\GeoCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class CalibrationSchemaUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [FestivalMapperServiceProvider::class];
    }

    public function test_legacy_database_preserves_points_and_accepts_geographic_calibration(): void
    {
        $festival = Festival::create(['name' => 'Legacy Festival', 'year' => 2026]);

        Schema::drop('festival_mapper_calibration_points');
        Schema::create('festival_mapper_calibration_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('festival_id')->constrained('festival_mapper_festivals');
            $table->double('pixel_x');
            $table->double('pixel_y');
            $table->double('internal_x');
            $table->double('internal_y');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        DB::table('festival_mapper_calibration_points')->insert([
            'festival_id' => $festival->id,
            'pixel_x' => 100,
            'pixel_y' => 200,
            'internal_x' => 5000,
            'internal_y' => 6000,
            'label' => 'Legacy point',
        ]);

        $migration = require __DIR__.'/../../database/migrations/2026_10_04_000005_upgrade_calibration_points_to_geographic_coordinates.php';
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('festival_mapper_calibration_points', [
            'label' => 'Legacy point',
            'internal_x' => 5000,
            'internal_y' => 6000,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->postJson("/api/festival-mapper/festivals/{$festival->id}/calibration", [
            'pixel_x' => 150,
            'pixel_y' => 250,
            'latitude' => 52.8317,
            'longitude' => -1.3672,
            'label' => 'Geographic point',
        ])->assertCreated()->assertJsonPath('label', 'Geographic point');

        $this->assertDatabaseHas('festival_mapper_calibration_points', [
            'label' => 'Geographic point',
            'latitude' => 52.8317,
            'longitude' => -1.3672,
            'internal_x' => null,
            'internal_y' => null,
        ]);

        $transformer = $this->mock(CoordinateTransformerInterface::class);
        $transformer->shouldReceive('toGeo')->once()
            ->withArgs(fn ($pixel, $anchors) => count($anchors) === 1
                && $anchors[0] instanceof \FestivalMapper\ValueObjects\CalibrationAnchor)
            ->andReturn(new GeoCoordinate(52.8317, -1.3672));

        $result = (new CoordinateEngine($transformer))->pixelToGeo(
            $festival, new PixelCoordinate(150, 250)
        );
        $this->assertInstanceOf(GeoCoordinate::class, $result);
    }

    public function test_upgrade_keeps_fresh_geographic_schema_and_saved_points(): void
    {
        $festival = Festival::create(['name' => 'Fresh Festival', 'year' => 2026]);
        $festival->calibrationPoints()->create([
            'pixel_x' => 100,
            'pixel_y' => 200,
            'latitude' => 52.8317,
            'longitude' => -1.3672,
            'label' => 'Existing geographic point',
        ]);

        $migration = require __DIR__.'/../../database/migrations/2026_10_04_000005_upgrade_calibration_points_to_geographic_coordinates.php';
        $migration->up();

        $this->assertFalse(Schema::hasColumn('festival_mapper_calibration_points', 'internal_x'));
        $this->assertDatabaseHas('festival_mapper_calibration_points', [
            'label' => 'Existing geographic point',
            'latitude' => 52.8317,
            'longitude' => -1.3672,
        ]);
    }
}
