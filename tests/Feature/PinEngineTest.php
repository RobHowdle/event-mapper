<?php

namespace FestivalMapper\Tests\Feature;

use FestivalMapper\Engines\PinEngine;
use FestivalMapper\FestivalMapperServiceProvider;
use FestivalMapper\Models\Festival;
use FestivalMapper\Models\Pin;
use FestivalMapper\ValueObjects\GeoCoordinate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;

class PinEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            FestivalMapperServiceProvider::class,
        ];
    }

    public function test_create_pin_creates_pin_with_coordinates_label_and_metadata(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $coordinate = new GeoCoordinate(
            54.6,
            -1.6,
        );

        $pin = app(PinEngine::class)->createPin(
            $festival,
            $coordinate,
            'Main Stage',
            [
                'type' => 'stage',
                'colour' => 'red',
            ],
        );

        $this->assertInstanceOf(Pin::class, $pin);

        $this->assertSame($festival->id, $pin->festival_id);
        $this->assertEqualsWithDelta(54.6, $pin->latitude, 0.000001);
        $this->assertEqualsWithDelta(-1.6, $pin->longitude, 0.000001);
        $this->assertSame('Main Stage', $pin->label);
        $this->assertSame([
            'type' => 'stage',
            'colour' => 'red',
        ], $pin->metadata);

        $this->assertDatabaseHas('festival_mapper_pins', [
            'id' => $pin->id,
            'festival_id' => $festival->id,
            'label' => 'Main Stage',
        ]);
    }

    public function test_move_pin_updates_coordinates(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $pin = $festival->pins()->create([
            'latitude' => 54.5,
            'longitude' => -1.7,
            'label' => 'Main Stage',
        ]);

        $coordinate = new GeoCoordinate(
            54.7,
            -1.5,
        );

        $result = app(PinEngine::class)->movePin(
            $pin,
            $coordinate,
        );

        $this->assertSame($pin->id, $result->id);
        $this->assertEqualsWithDelta(54.7, $result->latitude, 0.000001);
        $this->assertEqualsWithDelta(-1.5, $result->longitude, 0.000001);

        $this->assertDatabaseHas('festival_mapper_pins', [
            'id' => $pin->id,
            'latitude' => 54.7,
            'longitude' => -1.5,
        ]);
    }

    public function test_delete_pin_removes_pin(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $pin = $festival->pins()->create([
            'latitude' => 54.6,
            'longitude' => -1.6,
            'label' => 'Main Stage',
        ]);

        app(PinEngine::class)->deletePin($pin);

        $this->assertDatabaseMissing('festival_mapper_pins', [
            'id' => $pin->id,
        ]);
    }

    public function test_pins_for_festival_returns_pins_in_creation_order(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $first = $festival->pins()->create([
            'latitude' => 54.5,
            'longitude' => -1.7,
            'label' => 'First',
        ]);

        $second = $festival->pins()->create([
            'latitude' => 54.6,
            'longitude' => -1.6,
            'label' => 'Second',
        ]);

        $third = $festival->pins()->create([
            'latitude' => 54.7,
            'longitude' => -1.5,
            'label' => 'Third',
        ]);

        $pins = app(PinEngine::class)->pinsForFestival($festival);

        $this->assertCount(3, $pins);

        $this->assertSame([
            $first->id,
            $second->id,
            $third->id,
        ], $pins->pluck('id')->all());

        $this->assertSame([
            'First',
            'Second',
            'Third',
        ], $pins->pluck('label')->all());
    }
}
