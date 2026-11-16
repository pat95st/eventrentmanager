<?php

declare(strict_types=1);

namespace Tests\Unit\Application\MinuteProgram;

use App\Application\MinuteProgram\Command\FinishMinuteProgram;
use App\Application\MinuteProgram\Command\FinishMinuteProgramHandler;
use App\Application\MinuteProgram\Command\ResetMinuteProgramLive;
use App\Application\MinuteProgram\Command\ResetMinuteProgramLiveHandler;
use App\Application\MinuteProgram\Command\SetMinuteProgramDelay;
use App\Application\MinuteProgram\Command\SetMinuteProgramDelayHandler;
use App\Application\MinuteProgram\Command\StartMinuteProgramItem;
use App\Application\MinuteProgram\Command\StartMinuteProgramItemHandler;
use App\Application\MinuteProgram\Query\CountdownPayload;
use App\Application\MinuteProgram\Query\MinuteProgramDetail;
use App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder;
use App\Application\MinuteProgram\Query\MinuteProgramTradeResolver;
use App\Application\MinuteProgram\Query\RunSheetPayload;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramItem;
use App\Domain\Technician\Trade;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryMinuteProgramItemRepository;
use Tests\Support\Fakes\InMemoryMinuteProgramRepository;
use Tests\Support\Fakes\InMemoryTradeRepository;

/**
 * Live run sheet (migration 158): program starts 20:00 with
 * Einlass (10 min) → Begrüßung (5 min) → Keynote (30 min) → Ende (no duration).
 */
final class RunSheetLiveTest extends Unit
{
    private InMemoryMinuteProgramRepository $programs;
    private InMemoryMinuteProgramItemRepository $items;
    private MinuteProgramDetailBuilder $builder;
    private InMemoryTradeRepository $trades;

    protected function _before(): void
    {
        $this->programs = new InMemoryMinuteProgramRepository();
        $this->items = new InMemoryMinuteProgramItemRepository();
        $this->trades = new InMemoryTradeRepository();
        $at = new DateTimeImmutable('2026-11-01 10:00:00');
        $this->trades->save(new Trade('t-light', 'Licht', 20, $at, $at));
        $this->trades->save(new Trade('t-sound', 'Ton', 10, $at, $at));
        $this->trades->save(new Trade('t-rig', 'Rigging', 30, $at, $at));
        $this->trades->saveTradeIdsForRental('r1', ['t-light']);
        $this->builder = new MinuteProgramDetailBuilder($this->items, new MinuteProgramTradeResolver($this->trades));

        $this->programs->save(new MinuteProgram(
            id: 'p1',
            rentalId: 'r1',
            name: 'Gala',
            room: 'Saal',
            startAt: new DateTimeImmutable('2026-11-15 20:00:00'),
            jingleBefore: false,
            handheldMicCount: 2,
            headsetMicCount: 1,
            currentDelaySeconds: 0,
            createdAt: new DateTimeImmutable('2026-11-01 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-11-01 10:00:00'),
        ));
        foreach ([['i1', 'Einlass', 10], ['i2', 'Begrüßung', 5], ['i3', 'Keynote', 30], ['i4', 'Ende', null]] as $n => [$id, $name, $duration]) {
            $this->items->save(new MinuteProgramItem($id, 'p1', $n + 1, $name, null, null, $id === 'i3' ? ['t-sound' => 'Headset 1', 't-rig' => 'Truss runter'] : [], $duration));
        }
    }

    private function detail(string $at): MinuteProgramDetail
    {
        $program = $this->programs->findById('p1');
        $this->assertNotNull($program);

        return $this->builder->build($program, new DateTimeImmutable('2026-11-15 ' . $at));
    }

    private function start(string $itemId, string $at): int
    {
        return (new StartMinuteProgramItemHandler($this->programs, $this->items))
            ->handle(new StartMinuteProgramItem('p1', $itemId, new DateTimeImmutable('2026-11-15 ' . $at)));
    }

    public function testClockModeFollowsTheDelayedSchedule(): void
    {
        (new SetMinuteProgramDelayHandler($this->programs))->handle(new SetMinuteProgramDelay('p1', 150));
        $detail = $this->detail('20:12:00');

        $this->assertSame('i1', $detail->currentItem()?->item->id);
        $this->assertSame('i2', $detail->nextItem()?->item->id);
        $this->assertSame('20:12:30', $detail->nextItem()?->currentStart->format('H:i:s'));
        $this->assertSame('20:10:00', $detail->nextItem()?->plannedStart->format('H:i:s'));
        $this->assertSame(150, $detail->projectedDelay());
        $this->assertFalse($detail->finished);
    }

    public function testBeforeTheStartTheFirstItemIsNext(): void
    {
        $detail = $this->detail('19:30:00');

        $this->assertNull($detail->currentItem());
        $this->assertSame('i1', $detail->nextItem()?->item->id);
        $this->assertSame('pending', RunSheetPayload::build($detail)['live']['state']);
    }

    public function testStartDerivesTheDelayFromThePlannedStart(): void
    {
        $delay = $this->start('i2', '20:13:20');

        $this->assertSame(200, $delay);
        $detail = $this->detail('20:14:00');
        $this->assertSame('i2', $detail->currentItem()?->item->id);
        $this->assertSame('20:13:20', $detail->currentItem()?->actualStart?->format('H:i:s'));
        // Keynote planned 20:15 → now 20:18:20
        $this->assertSame('20:18:20', $detail->nextItem()?->currentStart->format('H:i:s'));
    }

    public function testStartedItemStaysCurrentAndOverrunGrowsTheProjectedDelay(): void
    {
        $this->start('i2', '20:10:00'); // on time, ends 20:15
        $detail = $this->detail('20:17:30');

        // Clock mode would already show the keynote – tracking keeps the greeting.
        $this->assertSame('i2', $detail->currentItem()?->item->id);
        $this->assertSame('i3', $detail->nextItem()?->item->id);
        $this->assertSame(150, $detail->overrunSeconds);
        $this->assertSame(150, $detail->projectedDelay());
        $this->assertSame(0, $detail->program->currentDelaySeconds);
        $this->assertSame('20:17:30', $detail->nextItem()?->projectedStart?->format('H:i:s'));
        $this->assertSame('20:15:00', $detail->nextItem()?->currentStart->format('H:i:s'));
    }

    public function testStartingAnEarlierItemAgainMovesBack(): void
    {
        $this->start('i3', '20:20:00');
        $delay = $this->start('i2', '20:21:00');

        $this->assertSame(660, $delay);
        $this->assertSame('i2', $this->detail('20:22:00')->currentItem()?->item->id);
    }

    public function testStartRejectsForeignItems(): void
    {
        $this->expectException(NotFoundException::class);
        $this->start('unknown', '20:00:00');
    }

    public function testFinishAndReset(): void
    {
        $this->start('i4', '20:50:00');
        (new FinishMinuteProgramHandler($this->programs))->handle(new FinishMinuteProgram('p1', new DateTimeImmutable('2026-11-15 21:00:00')));
        $detail = $this->detail('21:01:00');

        $this->assertTrue($detail->finished);
        $this->assertNull($detail->currentItem());
        $this->assertNull($detail->nextItem());
        $payload = RunSheetPayload::build($detail);
        $this->assertSame('finished', $payload['live']['state']);
        $this->assertSame(['done'], array_values(array_unique(array_column($payload['items'], 'status'))));

        (new ResetMinuteProgramLiveHandler($this->programs))->handle(new ResetMinuteProgramLive('p1'));
        $program = $this->programs->findById('p1');
        $this->assertSame([0, null, null, null], [$program?->currentDelaySeconds, $program?->liveItemId, $program?->liveItemStartedAt, $program?->liveFinishedAt]);
    }

    public function testClockModeFinishesAfterTheLastTimedItem(): void
    {
        $this->items->delete('i4');
        $this->assertFalse($this->detail('20:44:00')->finished);
        $detail = $this->detail('20:46:00');
        $this->assertTrue($detail->finished);
        $this->assertNull($detail->currentItem());
    }

    public function testPayloadContainsEverythingAStageDisplayNeeds(): void
    {
        $this->start('i2', '20:11:00');
        $payload = RunSheetPayload::build($this->detail('20:12:00'), ['eventName' => 'Firmenfeier', 'canControl' => true]);

        $this->assertSame(1, $payload['apiVersion']);
        $this->assertSame('Firmenfeier', $payload['event']['name']);
        $this->assertSame('running', $payload['live']['state']);
        $this->assertTrue($payload['live']['tracking']);
        $this->assertSame(60, $payload['live']['delaySeconds']);
        $this->assertSame('Begrüßung', $payload['current']['name']);
        $this->assertSame(60, $payload['current']['elapsedSeconds']);
        $this->assertSame(240, $payload['current']['remainingSeconds']);
        $this->assertSame('Keynote', $payload['next']['name']);
        $this->assertSame('Headset 1', $payload['next']['notes']['sound']);
        $this->assertSame(4, $payload['program']['itemCount']);
        $this->assertSame((new DateTimeImmutable('2026-11-15 20:45:00'))->format(DATE_ATOM), $payload['program']['plannedEnd']);
        $this->assertCount(4, $payload['items']);
        $this->assertSame(['done', 'current', 'next', 'upcoming'], array_column($payload['items'], 'status'));

        // Revision is stable over time and changes with the live state.
        $this->assertSame($payload['revision'], RunSheetPayload::build($this->detail('20:13:00'))['revision']);
        $this->start('i3', '20:16:00');
        $this->assertNotSame($payload['revision'], RunSheetPayload::build($this->detail('20:16:30'))['revision']);
    }

    public function testTradeNotesAreGroupedByOfferedTrades(): void
    {
        // Automatic: event trade (Licht) plus trades with notes (Ton, Rigging), in global order.
        $payload = RunSheetPayload::build($this->detail('19:00:00'));
        $this->assertSame(['Ton', 'Licht', 'Rigging'], array_column($payload['trades'], 'name'));
        $this->assertCount(3, array_unique(array_column($payload['trades'], 'color')));
        $keynote = $payload['items'][2];
        $this->assertSame(['Ton', 'Rigging'], array_column($keynote['tradeNotes'], 'trade'));
        $this->assertSame('Truss runter', $keynote['tradeNotes'][1]['text']);

        // Explicit selection hides notes of trades that are not selected (but keeps them stored).
        $program = $this->programs->findById('p1');
        $this->assertNotNull($program);
        $program->tradeIds = ['t-rig', 't-light'];
        $this->programs->save($program);
        $payload = RunSheetPayload::build($this->detail('19:00:00'));
        $this->assertSame(['Licht', 'Rigging'], array_column($payload['trades'], 'name'));
        $this->assertSame(['Rigging'], array_column($payload['items'][2]['tradeNotes'], 'trade'));
        $this->assertNull($payload['items'][2]['notes']['sound']);
        $this->assertSame('Headset 1', $this->items->findById('i3')?->tradeNotes['t-sound']);
    }

    public function testCountdownUsesProjectedStart(): void
    {
        $this->start('i2', '20:10:00');
        $countdown = CountdownPayload::build($this->detail('20:16:00'));

        $this->assertSame('Keynote', $countdown['nextLabel']);
        $this->assertSame(60, $countdown['delaySeconds']);
        $this->assertSame((new DateTimeImmutable('2026-11-15 20:16:00'))->format(DATE_ATOM), $countdown['nextAt']);
    }

    public function testNoStrobeFlagsAreExposedAndChangeTheRevision(): void
    {
        $before = RunSheetPayload::build($this->detail('20:12:00'));
        $this->assertFalse($before['event']['noStrobe']);
        $this->assertFalse($before['current']['noStrobe']);

        $item = $this->items->findById('i2');
        $this->assertNotNull($item);
        $item->noStrobe = true;
        $this->items->save($item);

        $after = RunSheetPayload::build($this->detail('20:12:00'), ['eventNoStrobe' => true]);
        $this->assertTrue($after['event']['noStrobe']);
        $this->assertTrue($after['current']['noStrobe']);
        $this->assertFalse($after['next']['noStrobe']);
        $this->assertFalse($after['items'][0]['noStrobe']);
        $this->assertTrue($after['items'][1]['noStrobe']);
        $this->assertNotSame($before['revision'], $after['revision']);
    }
}
