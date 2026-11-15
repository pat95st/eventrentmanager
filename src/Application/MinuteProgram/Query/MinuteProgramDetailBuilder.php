<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramSchedule;
use DateTimeImmutable;

/**
 * Baut aus einem Minutenprogramm + seinen Punkten das vollständige
 * Lesemodell MinuteProgramDetail: berechnete plannedStart/currentStart je
 * Punkt (kumulative Dauer-Summe plus aktuelle Verzögerung) sowie
 * isCurrent/isNext-Marker für die Live-Ansichten.
 *
 * Two modes determine the current item:
 *  - clock mode (default): the current item is the last one whose delayed
 *    start has passed, the next one is the first after it;
 *  - live tracking (an item was started at FOH, migration 157): the started
 *    item stays current until the next one is started. If it runs past the
 *    next item's start, the overrun is added to the projected start of all
 *    following items and to the projected delay.
 * A program marked as finished has neither a current nor a next item.
 *
 * Bewusst eine eigenständige, plain Service-Klasse (nur Repository-
 * Interfaces injiziert, KEINE CommandBus/QueryBus-Abhängigkeit) — wird
 * sowohl vom internen Zugriff (GetMinuteProgramDetailHandler) als auch vom
 * Freigabe-Link-Zugriff (ResolveMinuteProgramShareLinkHandler) benötigt;
 * im gesamten übrigen Code bekommt kein Application-Handler jemals selbst
 * einen CommandBus/QueryBus injiziert.
 */
final class MinuteProgramDetailBuilder
{
    public function __construct(private readonly MinuteProgramItemRepositoryInterface $items)
    {
    }

    public function build(MinuteProgram $program, ?DateTimeImmutable $now = null): MinuteProgramDetail
    {
        $items = array_values($this->items->findByMinuteProgram($program->id));
        $now ??= new DateTimeImmutable();
        $offsets = MinuteProgramSchedule::plannedOffsets($items);
        $delay = sprintf('%+d seconds', $program->currentDelaySeconds);

        $rows = [];
        $liveIndex = null;
        foreach ($items as $index => $item) {
            $plannedStart = $program->startAt->modify('+' . $offsets[$item->id] . ' minutes');
            $rows[] = [$item, $plannedStart, $plannedStart->modify($delay)];
            if ($program->isTrackedLive() && $item->id === $program->liveItemId) {
                $liveIndex = $index;
            }
        }

        $finished = $program->liveFinishedAt !== null;
        $currentIndex = null;
        $nextIndex = null;
        $overrun = 0;

        if ($finished) {
            // Nothing current, nothing next.
        } elseif ($liveIndex !== null) {
            $currentIndex = $liveIndex;
            $nextIndex = isset($rows[$liveIndex + 1]) ? $liveIndex + 1 : null;
            $item = $rows[$liveIndex][0];
            // Overrun = time the current item runs past its (delayed) end,
            // i.e. past the next item's scheduled start.
            $scheduledEnd = $nextIndex !== null
                ? $rows[$nextIndex][2]
                : ($item->durationMinutes !== null ? $rows[$liveIndex][2]->modify('+' . $item->durationMinutes . ' minutes') : null);
            if ($scheduledEnd !== null && $now > $scheduledEnd) {
                $overrun = $now->getTimestamp() - $scheduledEnd->getTimestamp();
            }
        } else {
            foreach ($rows as $index => [, , $currentStart]) {
                if ($currentStart <= $now) {
                    $currentIndex = $index;
                }
            }
            $nextIndex = $currentIndex === null ? ($rows !== [] ? 0 : null) : (isset($rows[$currentIndex + 1]) ? $currentIndex + 1 : null);
            // Last item over (only decidable with a duration; without one it
            // stays current until the program is finished at FOH).
            if ($currentIndex !== null && $nextIndex === null) {
                $last = $rows[$currentIndex][0];
                if ($last->durationMinutes !== null && $rows[$currentIndex][2]->modify('+' . $last->durationMinutes . ' minutes') <= $now) {
                    $finished = true;
                    $currentIndex = null;
                }
            }
        }

        $result = [];
        foreach ($rows as $index => [$item, $plannedStart, $currentStart]) {
            $status = match (true) {
                $index === $currentIndex => MinuteProgramItemDetail::STATUS_CURRENT,
                $index === $nextIndex => MinuteProgramItemDetail::STATUS_NEXT,
                $finished, $currentIndex !== null && $index < $currentIndex => MinuteProgramItemDetail::STATUS_DONE,
                default => MinuteProgramItemDetail::STATUS_UPCOMING,
            };
            $isAhead = $currentIndex === null || $index > $currentIndex;
            $result[] = new MinuteProgramItemDetail(
                item: $item,
                plannedStart: $plannedStart,
                currentStart: $currentStart,
                isCurrent: $index === $currentIndex,
                isNext: $index === $nextIndex,
                projectedStart: $isAhead && $overrun > 0 ? $currentStart->modify('+' . $overrun . ' seconds') : $currentStart,
                actualStart: $index === $liveIndex ? $program->liveItemStartedAt : null,
                status: $status,
                position: $index + 1,
            );
        }

        return new MinuteProgramDetail(
            program: $program,
            items: $result,
            computedAt: $now,
            projectedDelaySeconds: $program->currentDelaySeconds + $overrun,
            overrunSeconds: $overrun,
            finished: $finished,
        );
    }
}
