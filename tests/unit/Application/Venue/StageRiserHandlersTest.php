<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Venue;

use App\Application\Venue\Command\AddStageRiser;
use App\Application\Venue\Command\AddStageRiserHandler;
use App\Application\Venue\Command\DeleteStageRiser;
use App\Application\Venue\Command\DeleteStageRiserHandler;
use App\Application\Venue\Command\MoveStageRiser;
use App\Application\Venue\Command\MoveStageRiserHandler;
use App\Application\Venue\Command\UpdateStageRiser;
use App\Application\Venue\Command\UpdateStageRiserHandler;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Venue\RiserShape;
use App\Domain\Venue\Stage;
use App\Domain\Venue\StageType;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryVenueStageRepository;
use Tests\Support\Fakes\InMemoryVenueStageRiserRepository;

final class StageRiserHandlersTest extends Unit
{
    private InMemoryVenueStageRepository $stages;
    private InMemoryVenueStageRiserRepository $risers;
    private AddStageRiserHandler $add;
    private UpdateStageRiserHandler $update;
    private MoveStageRiserHandler $move;
    private DeleteStageRiserHandler $delete;

    protected function _before(): void
    {
        $this->stages = new InMemoryVenueStageRepository();
        $this->stages->save($this->stage('stage-1', 'venue-1', 6.0, 4.0));
        $this->stages->save($this->stage('stage-other', 'venue-1', 10.0, 8.0));
        // Mobile Bühne aus Elementbestand: keine festen Maße.
        $this->stages->save($this->stage('stage-stock', 'venue-1', null, null));

        $this->risers = new InMemoryVenueStageRiserRepository($this->stages);
        $this->add = new AddStageRiserHandler($this->risers, $this->stages);
        $this->update = new UpdateStageRiserHandler($this->risers, $this->stages);
        $this->move = new MoveStageRiserHandler($this->risers, $this->stages);
        $this->delete = new DeleteStageRiserHandler($this->risers, $this->stages);
    }

    public function testRectangleDefaultsToStageCentre(): void
    {
        $id = $this->add->handle(new AddStageRiser('venue-1', 'stage-1', ' Drum-Riser ', 'rectangle', '3', '2', '0,4'));

        $riser = $this->risers->findById($id);
        $this->assertSame('Drum-Riser', $riser?->name);
        $this->assertSame(RiserShape::Rectangle, $riser?->shape);
        $this->assertSame([3.0, 2.0, 0.4], [$riser?->widthM, $riser?->depthM, $riser?->heightM]);
        $this->assertSame([3.0, 2.0, 0], [$riser?->centerXM, $riser?->centerYM, $riser?->rotationDeg]);
        $this->assertSame(6.0, $riser?->areaSquareMeters());
    }

    public function testCircleNeedsOnlyADiameterAndIgnoresDepth(): void
    {
        $id = $this->add->handle(new AddStageRiser('venue-1', 'stage-1', '', 'circle', '1,5', 'egal', '0.2', '1', '1', '-90'));

        $riser = $this->risers->findById($id);
        $this->assertNull($riser?->name);
        $this->assertNull($riser?->depthM);
        $this->assertSame(1.5, $riser?->footprintDepthM());
        $this->assertSame(1.77, $riser?->areaSquareMeters());
        $this->assertSame(270, $riser?->rotationDeg);
    }

    public function testSemicircleAndTriangleFootprints(): void
    {
        $semi = $this->risers->findById($this->add->handle(new AddStageRiser('venue-1', 'stage-1', '', 'semicircle', '2', '', '0,2')));
        $this->assertSame(1.0, $semi?->footprintDepthM());
        $this->assertSame(1.57, $semi?->areaSquareMeters());

        $triangle = $this->risers->findById($this->add->handle(new AddStageRiser('venue-1', 'stage-1', '', 'triangle', '2', '1,5', '0,3')));
        $this->assertSame(1.5, $triangle?->areaSquareMeters());
    }

    public function testRejectsInvalidInput(): void
    {
        $errors = $this->errorsOf(fn () => $this->add->handle(
            new AddStageRiser('venue-1', 'stage-1', '', 'rectangle', '0', '', '0', '6,01', '-1', '400')
        ));
        $this->assertSame(['width', 'depth', 'height', 'centerX', 'centerY', 'rotation'], array_keys($errors));

        $this->assertSame(['shape'], array_keys($this->errorsOf(fn () => $this->add->handle(
            new AddStageRiser('venue-1', 'stage-1', '', 'stern', '1', '1', '0,2')
        ))));
    }

    public function testStageWithoutFixedDimensionsCannotHoldRisers(): void
    {
        $errors = $this->errorsOf(fn () => $this->add->handle(new AddStageRiser('venue-1', 'stage-stock', '', 'rectangle', '1', '1', '0,2')));

        $this->assertSame(['stage'], array_keys($errors));
    }

    public function testStageOfAnotherVenueIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->add->handle(new AddStageRiser('venue-2', 'stage-1', '', 'rectangle', '1', '1', '0,2'));
    }

    public function testUpdateChangesShapeAndDropsDepth(): void
    {
        $id = $this->add->handle(new AddStageRiser('venue-1', 'stage-1', 'Podest', 'rectangle', '3', '2', '0,4'));

        $this->update->handle(new UpdateStageRiser($id, 'venue-1', 'stage-1', 'Rund', 'circle', '2', '2', '0,6', '5', '3,5', '45'));

        $riser = $this->risers->findById($id);
        $this->assertSame(['Rund', RiserShape::Circle, 2.0, null, 0.6], [$riser?->name, $riser?->shape, $riser?->widthM, $riser?->depthM, $riser?->heightM]);
        $this->assertSame([5.0, 3.5, 45], [$riser?->centerXM, $riser?->centerYM, $riser?->rotationDeg]);
    }

    public function testMoveKeepsRiserCentreOnTheStage(): void
    {
        $id = $this->add->handle(new AddStageRiser('venue-1', 'stage-1', '', 'rectangle', '3', '2', '0,4'));

        $this->assertSame(['x' => 4.55, 'y' => 0.0], $this->move->handle(new MoveStageRiser($id, 'venue-1', 'stage-1', '4.55', '0.00')));
        $this->assertSame(4.55, $this->risers->findById($id)?->centerXM);

        // Außerhalb der Bühne, ohne Angabe oder über die falsche Bühne: Position bleibt.
        $this->assertSame(['centerX'], array_keys($this->errorsOf(fn () => $this->move->handle(new MoveStageRiser($id, 'venue-1', 'stage-1', '6.5', '1')))));
        $this->assertSame(['centerX'], array_keys($this->errorsOf(fn () => $this->move->handle(new MoveStageRiser($id, 'venue-1', 'stage-1', '', '')))));
        try {
            $this->move->handle(new MoveStageRiser($id, 'venue-1', 'stage-other', '1', '1'));
            $this->fail('Erwartete NotFoundException wurde nicht geworfen.');
        } catch (NotFoundException) {
            $this->assertSame([4.55, 0.0], [$this->risers->findById($id)?->centerXM, $this->risers->findById($id)?->centerYM]);
        }
    }

    public function testDeleteWorksEvenAfterStageLostItsDimensions(): void
    {
        $id = $this->add->handle(new AddStageRiser('venue-1', 'stage-1', '', 'rectangle', '3', '2', '0,4'));
        $this->assertSame(1, count($this->risers->findByVenueGroupedByStage('venue-1')['stage-1']));

        try {
            $this->delete->handle(new DeleteStageRiser($id, 'venue-2', 'stage-1'));
            $this->fail('Erwartete NotFoundException wurde nicht geworfen.');
        } catch (NotFoundException) {
            $this->assertNotNull($this->risers->findById($id));
        }

        $stage = $this->stages->findById('stage-1');
        $stage->widthM = null;
        $stage->depthM = null;

        $this->delete->handle(new DeleteStageRiser($id, 'venue-1', 'stage-1'));
        $this->assertNull($this->risers->findById($id));
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

    private function stage(string $id, string $venueId, ?float $width, ?float $depth): Stage
    {
        return new Stage(
            id: $id,
            venueId: $venueId,
            name: 'Bühne ' . $id,
            widthM: $width,
            depthM: $depth,
            heightM: 1.0,
            type: StageType::Mobile,
            powerDistances: [],
            createdAt: new DateTimeImmutable('2026-01-01 12:00:00'),
            elementStock: $width === null ? ['2x1' => 4] : [],
        );
    }
}
