<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use FestivalMapper\Models\Pin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class PinApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    private function festival(): Festival
    {
        return Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);
    }

    private function calibrationPoints(Festival $festival): void
    {
        $festival->calibrationPoints()->create([
            'pixel_x' => 0,
            'pixel_y' => 0,
            'latitude' => 54.0,
            'longitude' => -1.0,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 1000,
            'pixel_y' => 0,
            'latitude' => 54.0,
            'longitude' => 0.0,
        ]);

        $festival->calibrationPoints()->create([
            'pixel_x' => 0,
            'pixel_y' => 1000,
            'latitude' => 55.0,
            'longitude' => -1.0,
        ]);
    }

    public function test_index_returns_festival_pins(): void
    {
        $festival = $this->festival();

        $festival->pins()->create([
            'latitude' => 54.1,
            'longitude' => -1.2,
            'label' => 'Main Stage',
        ]);

        $festival->pins()->create([
            'latitude' => 54.2,
            'longitude' => -1.3,
            'label' => 'Food Area',
        ]);

        $response = $this->getJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins"
        );

        $response
            ->assertOk()
            ->assertJsonCount(2);

        $response->assertJsonFragment([
            'label' => 'Main Stage',
        ]);

        $response->assertJsonFragment([
            'label' => 'Food Area',
        ]);
    }

    public function test_store_creates_pin_from_geographic_coordinates(): void
    {
        $festival = $this->festival();

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins",
            [
                'latitude' => 54.1234,
                'longitude' => -1.5678,
                'label' => 'Main Stage',
                'metadata' => [
                    'type' => 'stage',
                ],
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'latitude' => 54.1234,
                'longitude' => -1.5678,
                'label' => 'Main Stage',
                'metadata' => [
                    'type' => 'stage',
                ],
            ]);

        $this->assertDatabaseHas('festival_mapper_pins', [
            'festival_id' => $festival->id,
            'latitude' => 54.1234,
            'longitude' => -1.5678,
            'label' => 'Main Stage',
        ]);
    }

    public function test_store_creates_pin_from_pixel_coordinates(): void
    {
        $festival = $this->festival();

        $this->calibrationPoints($festival);

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins",
            [
                'pixel_x' => 500,
                'pixel_y' => 500,
                'label' => 'Centre',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'label' => 'Centre',
            ]);

        $pin = Pin::query()->first();

        $this->assertNotNull($pin);

        $this->assertEqualsWithDelta(
            54.5,
            $pin->latitude,
            0.000001
        );

        $this->assertEqualsWithDelta(
            -0.5,
            $pin->longitude,
            0.000001
        );
    }

    public function test_store_requires_a_complete_coordinate_pair(): void
    {
        $festival = $this->festival();

        $response = $this->postJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins",
            [
                'latitude' => 54.0,
            ]
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'longitude',
        ]);
    }

    public function test_update_moves_pin_using_geographic_coordinates(): void
    {
        $festival = $this->festival();

        $pin = $festival->pins()->create([
            'latitude' => 54.0,
            'longitude' => -1.0,
            'label' => 'Main Stage',
        ]);

        $response = $this->patchJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins/{$pin->id}",
            [
                'latitude' => 55.1234,
                'longitude' => -2.5678,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'latitude' => 55.1234,
                'longitude' => -2.5678,
                'label' => 'Main Stage',
            ]);

        $this->assertDatabaseHas('festival_mapper_pins', [
            'id' => $pin->id,
            'latitude' => 55.1234,
            'longitude' => -2.5678,
        ]);
    }

    public function test_update_moves_pin_using_pixel_coordinates(): void
    {
        $festival = $this->festival();

        $this->calibrationPoints($festival);

        $pin = $festival->pins()->create([
            'latitude' => 54.0,
            'longitude' => -1.0,
            'label' => 'Main Stage',
        ]);

        $response = $this->patchJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins/{$pin->id}",
            [
                'pixel_x' => 500,
                'pixel_y' => 500,
            ]
        );

        $response->assertOk();

        $pin->refresh();

        $this->assertEqualsWithDelta(
            54.5,
            $pin->latitude,
            0.000001
        );

        $this->assertEqualsWithDelta(
            -0.5,
            $pin->longitude,
            0.000001
        );
    }

    public function test_update_can_change_label_and_metadata_without_moving_pin(): void
    {
        $festival = $this->festival();

        $pin = $festival->pins()->create([
            'latitude' => 54.1234,
            'longitude' => -1.5678,
            'label' => 'Old Label',
            'metadata' => [
                'type' => 'stage',
            ],
        ]);

        $response = $this->patchJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins/{$pin->id}",
            [
                'label' => 'New Label',
                'metadata' => [
                    'type' => 'food',
                    'indoor' => true,
                ],
            ]
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'latitude' => 54.1234,
                'longitude' => -1.5678,
                'label' => 'New Label',
            ]);

        $pin->refresh();

        $this->assertSame([
            'type' => 'food',
            'indoor' => true,
        ], $pin->metadata);
    }

    public function test_destroy_deletes_pin(): void
    {
        $festival = $this->festival();

        $pin = $festival->pins()->create([
            'latitude' => 54.0,
            'longitude' => -1.0,
            'label' => 'Main Stage',
        ]);

        $response = $this->deleteJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins/{$pin->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('festival_mapper_pins', [
            'id' => $pin->id,
        ]);
    }

    public function test_pin_from_another_festival_cannot_be_updated(): void
    {
        $festival = $this->festival();

        $otherFestival = Festival::create([
            'name' => 'Other Festival',
            'year' => 2027,
        ]);

        $pin = $otherFestival->pins()->create([
            'latitude' => 54.0,
            'longitude' => -1.0,
            'label' => 'Other Festival Pin',
        ]);

        $response = $this->patchJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins/{$pin->id}",
            [
                'label' => 'Hacked',
            ]
        );

        $response->assertNotFound();

        $pin->refresh();

        $this->assertSame(
            'Other Festival Pin',
            $pin->label
        );
    }

    public function test_pin_from_another_festival_cannot_be_deleted(): void
    {
        $festival = $this->festival();

        $otherFestival = Festival::create([
            'name' => 'Other Festival',
            'year' => 2027,
        ]);

        $pin = $otherFestival->pins()->create([
            'latitude' => 54.0,
            'longitude' => -1.0,
            'label' => 'Other Festival Pin',
        ]);

        $response = $this->deleteJson(
            "/api/festival-mapper/festivals/{$festival->id}/pins/{$pin->id}"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('festival_mapper_pins', [
            'id' => $pin->id,
        ]);
    }
}
