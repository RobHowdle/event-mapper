<?php

namespace FestivalMapper\Tests\Unit;

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

    public function test_pin_can_be_created_for_festival(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $coordinate = new GeoCoordinate(54.1234, -1.5678);

        $engine = new PinEngine();

        $pin = $engine->createPin(
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
        $this->assertSame(54.1234, $pin->latitude);
        $this->assertSame(-1.5678, $pin->longitude);
        $this->assertSame('Main Stage', $pin->label);
        $this->assertSame([
            'type' => 'stage',
            'colour' => 'red',
        ], $pin->metadata);
    }

    public function test_pin_can_be_moved(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $pin = $festival->pins()->create([
            'latitude' => 54.0,
            'longitude' => -1.0,
            'label' => 'Main Stage',
        ]);

        $engine = new PinEngine();

        $moved = $engine->movePin(
            $pin,
            new GeoCoordinate(55.1234, -2.5678),
        );

        $this->assertSame($pin->id, $moved->id);
        $this->assertSame(55.1234, $moved->latitude);
        $this->assertSame(-2.5678, $moved->longitude);
        $this->assertSame('Main Stage', $moved->label);
    }

    public function test_pin_can_be_deleted(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $pin = $festival->pins()->create([
            'latitude' => 54.0,
            'longitude' => -1.0,
            'label' => 'Main Stage',
        ]);

        $engine = new PinEngine();

        $engine->deletePin($pin);

        $this->assertDatabaseMissing(
            'festival_mapper_pins',
            ['id' => $pin->id]
        );
    }

    public function test_pins_for_festival_returns_pins_in_creation_order(): void
    {
        $festival = Festival::create([
            'name' => 'Test Festival',
            'year' => 2026,
        ]);

        $first = $festival->pins()->create([
            'latitude' => 54.0,
            'longitude' => -1.0,
            'label' => 'First',
        ]);

        $second = $festival->pins()->create([
            'latitude' => 55.0,
            'longitude' => -2.0,
            'label' => 'Second',
        ]);

        $third = $festival->pins()->create([
            'latitude' => 56.0,
            'longitude' => -3.0,
            'label' => 'Third',
        ]);

        $engine = new PinEngine();

        $pins = $engine->pinsForFestival($festival);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $pins->pluck('id')->all()
        );
    }
}
