<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Display;

use App\Application\Display\DisplayProgramResolver;
use App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder;
use App\Domain\Display\DisplayClient;
use App\Domain\Display\DisplayOptions;
use App\Domain\Display\DisplayRole;
use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramItem;
use App\Domain\Rental\Rental;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryMinuteProgramItemRepository;
use Tests\Support\Fakes\InMemoryMinuteProgramRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;

/**
 * Event r1 on 2026-11-15 with three 30-minute programs:
 * "Mittag" 12:00 (Saal), "Abend" 20:00 (Saal), "Foyer" 20:00 (Foyer).
 * Event r2 a week later with "Später" 18:00.
 */
final class DisplayProgramResolverTest extends Unit
{
    private InMemoryMinuteProgramRepository $programs;
    private DisplayProgramResolver $resolver;

    protected function _before(): void
    {
        $this->programs = new InMemoryMinuteProgramRepository();
        $items = new InMemoryMinuteProgramItemRepository();
        $rentals = new InMemoryRentalRepository();
        $at = new DateTimeImmutable('2026-11-01 10:00');
        $rentals->save(new Rental('r1', 'c1', new DateTimeImmutable('2026-11-15'), new DateTimeImmutable('2026-11-15'), 'Gala', null, null, 1, $at, $at));
        $rentals->save(new Rental('r2', 'c1', new DateTimeImmutable('2026-11-22'), new DateTimeImmutable('2026-11-22'), 'Messe', null, null, 1, $at, $at));
        foreach ([['p-noon', 'r1', 'Mittag', 'Saal', '2026-11-15 12:00'], ['p-eve', 'r1', 'Abend', 'Saal', '2026-11-15 20:00'], ['p-foyer', 'r1', 'Foyer', 'Foyer', '2026-11-15 20:00'], ['p-late', 'r2', 'Später', null, '2026-11-22 18:00']] as [$id, $rental, $name, $room, $start]) {
            $this->programs->save(new MinuteProgram($id, $rental, $name, $room, new DateTimeImmutable($start), false, 0, 0, 0, $at, $at));
            $items->save(new MinuteProgramItem($id . '-1', $id, 1, 'Teil 1', null, null, null, null, null, null, 30));
        }
        $this->resolver = new DisplayProgramResolver($this->programs, $rentals, new MinuteProgramDetailBuilder($items));
    }

    private function client(?string $rentalId = null, ?string $programId = null, ?string $room = null): DisplayClient
    {
        return new DisplayClient('d1', 'Monitor', DisplayRole::Stage, 'h', 'hint', true, $rentalId, $programId, $room, new DisplayOptions(), null, null, null, new DateTimeImmutable());
    }

    private function shown(DisplayClient $client, string $at): ?string
    {
        return $this->resolver->resolve($client, new DateTimeImmutable($at))?->program->id;
    }

    public function testPinnedProgramWins(): void
    {
        $this->assertSame('p-late', $this->shown($this->client('r1', 'p-late'), '2026-11-15 12:10'));
    }

    public function testEventPicksRunningThenUpcomingThenLast(): void
    {
        $client = $this->client('r1', null, 'saal ');
        $this->assertSame('p-noon', $this->shown($client, '2026-11-15 09:00'), 'upcoming before the first program');
        $this->assertSame('p-noon', $this->shown($client, '2026-11-15 12:10'), 'running');
        $this->assertSame('p-eve', $this->shown($client, '2026-11-15 14:00'), 'next upcoming after the first ended');
        $this->assertSame('p-eve', $this->shown($client, '2026-11-15 23:00'), 'last one after everything ended');
    }

    public function testRoomFilter(): void
    {
        $this->assertSame('p-foyer', $this->shown($this->client('r1', null, 'Foyer'), '2026-11-15 20:10'));
        $this->assertNull($this->shown($this->client('r1', null, 'Keller'), '2026-11-15 20:10'));
    }

    public function testFinishedProgramIsSkippedWhileAnotherIsUpcoming(): void
    {
        $noon = $this->programs->findById('p-noon');
        $this->assertNotNull($noon);
        $noon->liveFinishedAt = new DateTimeImmutable('2026-11-15 12:05');
        $this->programs->save($noon);
        $this->assertSame('p-eve', $this->shown($this->client('r1', null, 'Saal'), '2026-11-15 12:10'));
    }

    public function testAutomaticModeOnlyUsesEventsRunningToday(): void
    {
        $this->assertSame('p-noon', $this->shown($this->client(), '2026-11-15 12:10'));
        $this->assertSame('p-late', $this->shown($this->client(), '2026-11-21 10:00'), 'day before the event counts');
        $this->assertNull($this->shown($this->client(), '2026-11-18 10:00'));
    }
}
