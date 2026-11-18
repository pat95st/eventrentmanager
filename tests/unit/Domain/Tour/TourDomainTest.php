<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tour;

use App\Domain\Tour\TourAllocation;
use App\Domain\Tour\TourCrewAction;
use App\Domain\Tour\TourCrewResolver;
use App\Domain\Tour\TourMaterialResolver;
use App\Domain\Tour\TourPlan;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\TourStopItem;
use App\Domain\Tour\TourStopKind;
use App\Domain\Tour\TourStopTechnician;
use App\Domain\Tour\WaypointPurpose;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class TourDomainTest extends Unit
{
    private static function stop(string $id, string $start, string $city): TourStop
    {
        return new TourStop($id, 'tour', TourStopKind::Stop, locationText: $city, eventStartAt: new DateTimeImmutable($start), eventEndAt: new DateTimeImmutable($start . ' +4 hours'));
    }

    private static function waypoint(string $id, ?string $after, ?string $arrive = null): TourStop
    {
        return new TourStop($id, 'tour', TourStopKind::Waypoint, afterStopId: $after, purpose: WaypointPurpose::Overnight, label: 'Hotel ' . $id, arriveAt: $arrive !== null ? new DateTimeImmutable($arrive) : null);
    }

    public function testPlanOrdersStopsByStartAndPutsWaypointsBetween(): void
    {
        $plan = new TourPlan([
            self::stop('kassel', '2027-06-03 19:00', 'Kassel'),
            self::waypoint('hotel2', 'hamburg', '2027-06-02 01:00'),
            self::stop('hamburg', '2027-06-01 19:00', 'Hamburg'),
            self::waypoint('lager', null),
            self::waypoint('hotel1', 'hamburg', '2027-06-01 23:30'),
        ]);

        $this->assertSame(['lager', 'hamburg', 'hotel1', 'hotel2', 'kassel'], array_map(static fn (TourStop $s) => $s->id, $plan->ordered()));
        $this->assertSame(2, $plan->numberOf('kassel'));
        $this->assertSame(['kassel'], array_map(static fn (TourStop $s) => $s->id, $plan->stopsAfter('hamburg')));
    }

    public function testMaterialPerStopExtrasAndStartLoad(): void
    {
        $stops = [self::stop('s1', '2027-06-01 19:00', 'A'), self::stop('s2', '2027-06-02 19:00', 'B'), self::stop('s3', '2027-06-03 19:00', 'C')];
        $resolver = new TourMaterialResolver(
            ['top' => 4, 'sub' => 2],
            [
                new TourStopItem('i1', 's2', 'sub', 2, TourAllocation::StopsOnly),       // bigger PA in B
                new TourStopItem('i2', 's2', 'amp', 1, TourAllocation::WholeTour),
                new TourStopItem('i3', 's3', 'amp', 1, TourAllocation::WholeTour),
                new TourStopItem('i4', 's3', 'top', -4),                                   // no tops in C
            ],
            $stops,
        );

        $this->assertSame(['top' => 4, 'sub' => 2], $resolver->effective('s1'));
        $this->assertSame(['top' => 4, 'sub' => 4, 'amp' => 1], $resolver->effective('s2'));
        $this->assertSame(['sub' => 2, 'amp' => 1], $resolver->effective('s3'));
        $this->assertSame(['sub' => 2, 'amp' => 1], $resolver->extraForIssue());
        $this->assertSame(['top' => 4, 'sub' => 2, 'amp' => 1], $resolver->startLoad());
        $this->assertSame([
            ['articleId' => 'sub', 'before' => 2, 'after' => 4],
            ['articleId' => 'amp', 'before' => 0, 'after' => 1],
        ], $resolver->changesAt('s2'));
        $this->assertSame([
            ['articleId' => 'top', 'before' => 4, 'after' => 0],
            ['articleId' => 'sub', 'before' => 4, 'after' => 2],
        ], $resolver->changesAt('s3'));
    }

    public function testCrewPerStopWithReplacementAdditionAndRemoval(): void
    {
        $resolver = new TourCrewResolver(['max', 'eva'], [
            new TourStopTechnician('d1', 's2', TourCrewAction::Replace, 'lisa', 'max'),
            new TourStopTechnician('d2', 's2', TourCrewAction::Add, 'tom'),
            new TourStopTechnician('d3', 's3', TourCrewAction::Remove, 'eva'),
        ]);

        $this->assertSame(['max', 'eva'], array_keys($resolver->crewFor('s1')));
        $crew = $resolver->crewFor('s2');
        $this->assertSame(['eva', 'lisa', 'tom'], array_keys($crew));
        $this->assertSame(TourCrewAction::Replace, $crew['lisa']?->action);
        $this->assertSame(['max'], array_keys($resolver->crewFor('s3')));
    }
}
