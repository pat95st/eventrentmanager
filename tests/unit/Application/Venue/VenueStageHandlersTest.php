<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Venue;

use App\Application\Venue\Command\AddVenueStage;
use App\Application\Venue\Command\AddVenueStageHandler;
use App\Application\Venue\Command\DeleteVenueStage;
use App\Application\Venue\Command\DeleteVenueStageHandler;
use App\Application\Venue\Command\UpdateVenueStage;
use App\Application\Venue\Command\UpdateVenueStageHandler;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\CircuitBreakerRating;
use App\Domain\Venue\PowerConnection;
use App\Domain\Venue\PowerConnectionType;
use App\Domain\Venue\RcdTripCurrent;
use App\Domain\Venue\StageType;
use App\Domain\Venue\Venue;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryVenuePowerConnectionRepository;
use Tests\Support\Fakes\InMemoryVenueRepository;
use Tests\Support\Fakes\InMemoryVenueStageRepository;

final class VenueStageHandlersTest extends Unit
{
    private InMemoryVenueStageRepository $stages;
    private InMemoryVenuePowerConnectionRepository $connections;
    private AddVenueStageHandler $add;
    private UpdateVenueStageHandler $update;
    private DeleteVenueStageHandler $delete;

    protected function _before(): void
    {
        $venues = new InMemoryVenueRepository();
        $venues->save($this->venue('venue-1'));
        $venues->save($this->venue('venue-2'));

        $this->connections = new InMemoryVenuePowerConnectionRepository();
        $this->connections->save($this->connection('conn-a', 'venue-1'));
        $this->connections->save($this->connection('conn-b', 'venue-1'));
        $this->connections->save($this->connection('conn-other', 'venue-2'));

        $this->stages = new InMemoryVenueStageRepository();
        $this->add = new AddVenueStageHandler($this->stages, $venues, $this->connections);
        $this->update = new UpdateVenueStageHandler($this->stages, $this->connections);
        $this->delete = new DeleteVenueStageHandler($this->stages);
    }

    public function testAddsStageWithGermanDecimalsAndSkipsEmptyDistances(): void
    {
        $id = $this->add->handle(new AddVenueStage(
            venueId: 'venue-1',
            name: '  Hauptbühne ',
            width: '10',
            depth: '8,5',
            height: '1.2 m',
            type: 'fixed',
            powerDistances: ['conn-a' => '12,5', 'conn-b' => '  '],
        ));

        $stage = $this->stages->findById($id);
        $this->assertNotNull($stage);
        $this->assertSame('Hauptbühne', $stage->name);
        $this->assertSame(10.0, $stage->widthM);
        $this->assertSame(8.5, $stage->depthM);
        $this->assertSame(1.2, $stage->heightM);
        $this->assertSame(StageType::Fixed, $stage->type);
        $this->assertSame(['conn-a' => 12.5], $stage->powerDistances);
        $this->assertSame(85.0, $stage->areaSquareMeters());
        $this->assertNull($stage->distanceTo('conn-b'));
    }

    public function testAllowsGroundLevelStageButRejectsZeroFootprint(): void
    {
        $id = $this->add->handle(new AddVenueStage('venue-1', 'Ebenerdig', '4', '3', '0', 'mobile'));
        $this->assertSame(0.0, $this->stages->findById($id)?->heightM);

        $errors = $this->errorsOf(fn () => $this->add->handle(new AddVenueStage('venue-1', 'Kaputt', '0', '-3', '', 'mobile')));
        $this->assertSame(['width', 'depth', 'height'], array_keys($errors));
    }

    public function testRejectsMissingNameUnknownTypeAndInvalidDistance(): void
    {
        $errors = $this->errorsOf(fn () => $this->add->handle(new AddVenueStage(
            venueId: 'venue-1',
            name: ' ',
            width: '6',
            depth: '4',
            height: '1',
            type: 'schwebend',
            powerDistances: ['conn-a' => 'weit'],
        )));

        $this->assertSame(['name', 'type', 'powerDistances'], array_keys($errors));
        $this->assertSame([], $this->stages->findByVenue('venue-1'));
    }

    public function testRejectsDistanceToPowerConnectionOfAnotherVenue(): void
    {
        $errors = $this->errorsOf(fn () => $this->add->handle(new AddVenueStage(
            venueId: 'venue-1',
            name: 'Bühne',
            width: '6',
            depth: '4',
            height: '1',
            type: 'fixed',
            powerDistances: ['conn-other' => '5'],
        )));

        $this->assertArrayHasKey('powerDistances', $errors);
    }

    public function testMobileStageFromElementStockHasNoFixedDimensions(): void
    {
        $id = $this->add->handle(new AddVenueStage(
            venueId: 'venue-1',
            name: 'Podestbühne',
            width: '',
            depth: '',
            height: '0,6',
            type: 'mobile',
            buildMode: 'elements',
            elementStock: ['2x1' => '12', '1x1' => '4', '2x0.5' => '', '1x0.5' => '0'],
        ));

        $stage = $this->stages->findById($id);
        $this->assertNull($stage?->widthM);
        $this->assertNull($stage?->areaSquareMeters());
        $this->assertSame(true, $stage?->isDefinedByElementStock());
        $this->assertSame(['2x1' => 12, '1x1' => 4], $stage?->elementStock);
        $this->assertSame(28.0, $stage?->buildSuggestions()[0]->areaSquareMeters());
    }

    public function testElementStockNeedsAtLeastOneValidQuantity(): void
    {
        $empty = $this->errorsOf(fn () => $this->add->handle(new AddVenueStage('venue-1', 'Leer', '', '', '1', 'mobile', [], 'elements', [])));
        $this->assertSame(['elementStock'], array_keys($empty));

        $invalid = $this->errorsOf(fn () => $this->add->handle(new AddVenueStage('venue-1', 'Zu viel', '', '', '1', 'mobile', [], 'elements', ['2x1' => '201'])));
        $this->assertSame(['elementStock'], array_keys($invalid));
    }

    public function testElementStockIsIgnoredForFixedStagesAndFixedDimensions(): void
    {
        // Fest installiert: "elements" gibt es nicht, Maße bleiben Pflicht.
        $errors = $this->errorsOf(fn () => $this->add->handle(new AddVenueStage('venue-1', 'Fest', '', '', '1', 'fixed', [], 'elements', ['2x1' => '4'])));
        $this->assertSame(['width', 'depth'], array_keys($errors));

        $id = $this->add->handle(new AddVenueStage('venue-1', 'Mobil 6x4', '6', '4', '1', 'mobile', [], 'dimensions', ['2x1' => '4']));
        $stage = $this->stages->findById($id);
        $this->assertSame([], $stage?->elementStock);
        $this->assertSame(['2x1' => 12], $stage?->buildSuggestions()[0]->counts());

        // Wechsel auf Elementbestand verwirft die Maße, Wechsel zurück den Bestand.
        $this->update->handle(new UpdateVenueStage($id, 'venue-1', 'Mobil', '6', '4', '1', 'mobile', [], 'elements', ['1x1' => '9']));
        $this->assertNull($this->stages->findById($id)?->widthM);
        $this->update->handle(new UpdateVenueStage($id, 'venue-1', 'Fest', '5', '3', '1', 'fixed', [], 'elements', ['1x1' => '9']));
        $this->assertSame(5.0, $this->stages->findById($id)?->widthM);
        $this->assertSame([], $this->stages->findById($id)?->elementStock);
        $this->assertSame([], $this->stages->findById($id)?->buildSuggestions());
    }

    public function testAddingToUnknownVenueFails(): void
    {
        $this->expectException(NotFoundException::class);
        $this->add->handle(new AddVenueStage('nope', 'Bühne', '6', '4', '1', 'fixed'));
    }

    public function testUpdateReplacesValuesAndDistances(): void
    {
        $id = $this->add->handle(new AddVenueStage('venue-1', 'Bühne', '6', '4', '1', 'fixed', ['conn-a' => '5']));

        $this->update->handle(new UpdateVenueStage($id, 'venue-1', 'Große Bühne', '12', '8', '1,5', 'mobile', ['conn-a' => '', 'conn-b' => '30']));

        $stage = $this->stages->findById($id);
        $this->assertSame('Große Bühne', $stage?->name);
        $this->assertSame(12.0, $stage?->widthM);
        $this->assertSame(StageType::Mobile, $stage?->type);
        $this->assertSame(['conn-b' => 30.0], $stage?->powerDistances);
    }

    public function testUpdateAndDeleteRefuseStageOfAnotherVenue(): void
    {
        $id = $this->add->handle(new AddVenueStage('venue-1', 'Bühne', '6', '4', '1', 'fixed'));

        try {
            $this->update->handle(new UpdateVenueStage($id, 'venue-2', 'Fremd', '1', '1', '1', 'fixed'));
            $this->fail('Erwartete NotFoundException wurde nicht geworfen.');
        } catch (NotFoundException) {
            $this->assertSame('Bühne', $this->stages->findById($id)?->name);
        }

        try {
            $this->delete->handle(new DeleteVenueStage($id, 'venue-2'));
            $this->fail('Erwartete NotFoundException wurde nicht geworfen.');
        } catch (NotFoundException) {
            $this->assertNotNull($this->stages->findById($id));
        }

        $this->delete->handle(new DeleteVenueStage($id, 'venue-1'));
        $this->assertNull($this->stages->findById($id));
    }

    /** @return array<string, string> */
    private function errorsOf(callable $action): array
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Erwartete ValidationException wurde nicht geworfen.');
    }

    private function venue(string $id): Venue
    {
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        return new Venue($id, 'Halle ' . $id, 'Hauptstr. 1', '12345', 'Teststadt', null, null, null, null, null, null, $now, $now);
    }

    private function connection(string $id, string $venueId): PowerConnection
    {
        return new PowerConnection(
            id: $id,
            venueId: $venueId,
            type: PowerConnectionType::Cee32A400V,
            breakerRating: CircuitBreakerRating::all()[0],
            rcdTripCurrent: RcdTripCurrent::all()[0],
            location: null,
            createdAt: new DateTimeImmutable('2026-01-01 12:00:00'),
        );
    }
}
