<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\CalibrationPoint;
use FestivalMapper\Models\Festival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class CalibrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    public function test_index_returns_festival_calibration_points(): void
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

        $response = $this->getJson(
            "/api/festival-mapper/festivals/{$festival->id}/calibration"
        );

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.label', 'Main Stage');
    }

    public function test_store_creates_calibration_point(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/calibration",
            [
                'pixel_x' => 100,
                'pixel_y' => 200,
                'latitude' => 54.6,
                'longitude' => -1.6,
                'label' => 'Main Stage',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('label', 'Main Stage');

        $this->assertDatabaseHas('festival_mapper_calibration_points', [
            'festival_id' => $festival->id,
            'pixel_x' => 100,
            'pixel_y' => 200,
            'latitude' => 54.6,
            'longitude' => -1.6,
            'label' => 'Main Stage',
        ]);
    }

    public function test_store_validates_coordinates(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/calibration",
            [
                'pixel_x' => 100,
                'pixel_y' => 200,
                'latitude' => 95,
                'longitude' => -1.6,
            ]
        );

        $response->assertUnprocessable();
    }

    public function test_update_modifies_calibration_point(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $point = $festival->calibrationPoints()->create([
            'pixel_x' => 100,
            'pixel_y' => 200,
            'latitude' => 54.6,
            'longitude' => -1.6,
            'label' => 'Old Label',
        ]);

        $response = $this->patchJson(
            "/api/festival-mapper/festivals/{$festival->id}/calibration/{$point->id}",
            [
                'pixel_x' => 150,
                'label' => 'New Label',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('pixel_x', 150)
            ->assertJsonPath('label', 'New Label');
    }

    public function test_destroy_deletes_calibration_point(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $point = $festival->calibrationPoints()->create([
            'pixel_x' => 100,
            'pixel_y' => 200,
            'latitude' => 54.6,
            'longitude' => -1.6,
        ]);

        $response = $this->deleteJson(
            "/api/festival-mapper/festivals/{$festival->id}/calibration/{$point->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing(
            'festival_mapper_calibration_points',
            ['id' => $point->id]
        );
    }

    public function test_calibration_point_from_another_festival_cannot_be_updated(): void
    {
        $festivalA = Festival::create([
            'name' => 'Festival A',
            'year' => 2026,
        ]);

        $festivalB = Festival::create([
            'name' => 'Festival B',
            'year' => 2027,
        ]);

        $point = $festivalB->calibrationPoints()->create([
            'pixel_x' => 100,
            'pixel_y' => 200,
            'latitude' => 54.6,
            'longitude' => -1.6,
        ]);

        $response = $this->patchJson(
            "/api/festival-mapper/festivals/{$festivalA->id}/calibration/{$point->id}",
            [
                'label' => 'Should Not Work',
            ]
        );

        $response->assertNotFound();
    }

    public function test_calibration_point_from_another_festival_cannot_be_deleted(): void
    {
        $festivalA = Festival::create([
            'name' => 'Festival A',
            'year' => 2026,
        ]);

        $festivalB = Festival::create([
            'name' => 'Festival B',
            'year' => 2027,
        ]);

        $point = $festivalB->calibrationPoints()->create([
            'pixel_x' => 100,
            'pixel_y' => 200,
            'latitude' => 54.6,
            'longitude' => -1.6,
        ]);

        $response = $this->deleteJson(
            "/api/festival-mapper/festivals/{$festivalA->id}/calibration/{$point->id}"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'festival_mapper_calibration_points',
            ['id' => $point->id]
        );
    }
}
