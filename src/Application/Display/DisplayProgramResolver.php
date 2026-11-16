<?php

declare(strict_types=1);

namespace App\Application\Display;

use App\Application\MinuteProgram\Query\MinuteProgramDetail;
use App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder;
use App\Domain\Display\DisplayClient;
use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use DateTimeImmutable;

/**
 * Determines which minute program a display device shows (migration 168):
 *
 *  1. the pinned program ($client->minuteProgramId), if it still exists;
 *  2. otherwise the programs of the assigned event – or, without an event,
 *     of all events running today (from the day before the start until the
 *     day after the end, like the audio players) – limited to $client->room
 *     if set, and among them choose():
 *       a. a running program (started via FOH, or now between its delayed
 *          start and projected end) – the one started last;
 *       b. else the next upcoming one;
 *       c. else the one that started last (shows its final state).
 */
final class DisplayProgramResolver
{
    public function __construct(
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly RentalRepositoryInterface $rentals,
        private readonly MinuteProgramDetailBuilder $detailBuilder,
    ) {
    }

    public function resolve(DisplayClient $client, ?DateTimeImmutable $now = null): ?MinuteProgramDetail
    {
        $now ??= new DateTimeImmutable();
        if ($client->minuteProgramId !== null) {
            $program = $this->programs->findById($client->minuteProgramId);
            if ($program !== null) {
                return $this->detailBuilder->build($program, $now);
            }
        }

        $details = array_map(
            fn (MinuteProgram $p): MinuteProgramDetail => $this->detailBuilder->build($p, $now),
            array_values(array_filter($this->candidates($client, $now), static fn (MinuteProgram $p): bool => self::matchesRoom($p, $client->room))),
        );

        return self::choose($details, $now);
    }

    /** @return MinuteProgram[] */
    private function candidates(DisplayClient $client, DateTimeImmutable $now): array
    {
        if ($client->rentalId !== null) {
            return $this->programs->findByRental($client->rentalId);
        }
        $today = $now->setTime(0, 0);
        $programs = [];
        foreach ($this->rentals->findOverlapping($today->modify('-1 day'), $today->modify('+1 day')) as $rental) {
            array_push($programs, ...$this->programs->findByRental($rental->id));
        }

        return $programs;
    }

    private static function matchesRoom(MinuteProgram $program, ?string $room): bool
    {
        if ($room === null || trim($room) === '') {
            return true;
        }

        return mb_strtolower(trim((string) $program->room)) === mb_strtolower(trim($room));
    }

    /** @param MinuteProgramDetail[] $details */
    public static function choose(array $details, DateTimeImmutable $now): ?MinuteProgramDetail
    {
        $running = [];
        $upcoming = [];
        foreach ($details as $detail) {
            if ($detail->finished) {
                continue;
            }
            $start = $detail->program->delayedStartAt();
            if ($start > $now) {
                $upcoming[] = $detail;
            } elseif ($detail->program->isTrackedLive() || self::projectedEnd($detail) >= $now) {
                $running[] = $detail;
            }
        }
        $byStart = static fn (MinuteProgramDetail $a, MinuteProgramDetail $b): int => $a->program->delayedStartAt() <=> $b->program->delayedStartAt();

        if ($running !== []) {
            usort($running, $byStart);

            return $running[count($running) - 1];
        }
        if ($upcoming !== []) {
            usort($upcoming, $byStart);

            return $upcoming[0];
        }
        if ($details === []) {
            return null;
        }
        usort($details, $byStart);

        return $details[count($details) - 1];
    }

    private static function projectedEnd(MinuteProgramDetail $detail): DateTimeImmutable
    {
        $items = $detail->items;
        if ($items === []) {
            return $detail->program->delayedStartAt();
        }
        $last = $items[count($items) - 1];

        return $last->effectiveEnd() ?? $last->effectiveStart();
    }
}
