<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use DateTimeImmutable;

/**
 * Builds the live run sheet JSON (migration 158) used by the FOH page
 * (MinuteProgramFohController) and the stage display API
 * (RunSheetApiController, /api/runsheet/v1/...). Documented in
 * docs/stagedisplay-api.md – keep both in sync and only ADD fields within
 * API version 1.
 *
 * All timestamps are ISO 8601 with offset. Clients should compute running
 * countdowns locally from these timestamps, correcting their own clock with
 * "serverTime" (offset = serverTime − local time at receipt).
 */
final class RunSheetPayload
{
    public const API_VERSION = 1;

    /**
     * @param array{eventName?: ?string, eventNoStrobe?: bool, jingleTitle?: ?string, canControl?: bool} $context
     *
     * @return array<string, mixed>
     */
    public static function build(MinuteProgramDetail $detail, array $context = []): array
    {
        $program = $detail->program;
        $now = $detail->computedAt ?? new DateTimeImmutable();
        $current = $detail->currentItem();
        $next = $detail->nextItem();
        $items = $detail->items;
        $last = $items !== [] ? $items[count($items) - 1] : null;

        $plannedEnd = $last?->plannedEnd() ?? $last?->plannedStart ?? $program->startAt;
        $projectedEnd = $last !== null
            ? ($last->effectiveEnd() ?? $last->effectiveStart())
            : $program->delayedStartAt();

        $state = match (true) {
            $detail->finished => 'finished',
            $current !== null => 'running',
            default => 'pending',
        };

        return [
            'apiVersion' => self::API_VERSION,
            'serverTime' => $now->format(DATE_ATOM),
            // Changes whenever plan or live state change (not with the clock).
            'revision' => self::revision($detail, $context['eventNoStrobe'] ?? false),
            'event' => [
                'name' => $context['eventName'] ?? null,
                // Migration 165: no strobe allowed at the whole event.
                'noStrobe' => $context['eventNoStrobe'] ?? false,
            ],
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'room' => $program->room,
                'plannedStart' => self::time($program->startAt),
                'currentStart' => self::time($program->delayedStartAt()),
                'plannedEnd' => self::time($plannedEnd),
                'projectedEnd' => self::time($projectedEnd),
                'jingleBefore' => $program->jingleBefore,
                'jingleTitle' => $program->jingleBefore ? ($context['jingleTitle'] ?? null) : null,
                'handheldMicCount' => $program->handheldMicCount,
                'headsetMicCount' => $program->headsetMicCount,
                'itemCount' => count($items),
            ],
            'live' => [
                'state' => $state,
                // Manually tracked via "Start" (current item is held until the next start).
                'tracking' => $program->isTrackedLive() && !$detail->finished,
                'delaySeconds' => $program->currentDelaySeconds,
                // Delay including the running overrun of the current item.
                'projectedDelaySeconds' => $detail->projectedDelay(),
                'overrunSeconds' => $detail->overrunSeconds,
                'finishedAt' => self::time($program->liveFinishedAt),
                'canControl' => $context['canControl'] ?? false,
            ],
            'current' => $current !== null ? self::itemRef($current, $now) : null,
            'next' => $next !== null ? self::itemRef($next, $now) : null,
            'items' => array_map(static fn (MinuteProgramItemDetail $d): array => self::item($d), $items),
        ];
    }

    /** @return array<string, mixed> */
    private static function itemRef(MinuteProgramItemDetail $d, DateTimeImmutable $now): array
    {
        $start = $d->effectiveStart();
        $end = $d->effectiveEnd();
        $duration = $d->durationSeconds();
        $elapsed = $now->getTimestamp() - $start->getTimestamp();

        return [
            'id' => $d->item->id,
            'position' => $d->position,
            'name' => $d->item->name,
            'description' => $d->item->description,
            'notes' => self::notes($d),
            'noStrobe' => $d->item->noStrobe,
            'durationSeconds' => $duration,
            'startsAt' => self::time($start),
            'endsAt' => self::time($end),
            // Positive once started; for the next item negative = time until start.
            'elapsedSeconds' => $elapsed,
            'remainingSeconds' => $end !== null ? $end->getTimestamp() - $now->getTimestamp() : null,
            'actualStart' => self::time($d->actualStart),
        ];
    }

    /** @return array<string, mixed> */
    private static function item(MinuteProgramItemDetail $d): array
    {
        return [
            'id' => $d->item->id,
            'position' => $d->position,
            'name' => $d->item->name,
            'description' => $d->item->description,
            'notes' => self::notes($d),
            'noStrobe' => $d->item->noStrobe,
            'durationMinutes' => $d->item->durationMinutes,
            'status' => $d->status,
            'plannedStart' => self::time($d->plannedStart),
            'plannedEnd' => self::time($d->plannedEnd()),
            'currentStart' => self::time($d->currentStart),
            'projectedStart' => self::time($d->effectiveStart()),
            'projectedEnd' => self::time($d->effectiveEnd()),
            'actualStart' => self::time($d->actualStart),
        ];
    }

    /** @return array{general: ?string, sound: ?string, light: ?string, video: ?string, stage: ?string} */
    private static function notes(MinuteProgramItemDetail $d): array
    {
        return [
            'general' => $d->item->noteGeneral,
            'sound' => $d->item->noteSound,
            'light' => $d->item->noteLight,
            'video' => $d->item->noteVideo,
            'stage' => $d->item->noteStage,
        ];
    }

    private static function revision(MinuteProgramDetail $detail, bool $eventNoStrobe): string
    {
        $p = $detail->program;
        $parts = [$p->updatedAt->format(DATE_ATOM), $p->startAt->format(DATE_ATOM), $p->currentDelaySeconds, $p->liveItemId, $p->liveFinishedAt?->format(DATE_ATOM), $eventNoStrobe ? 1 : 0];
        foreach ($detail->items as $d) {
            $i = $d->item;
            $parts[] = implode('|', [$i->id, $i->sortOrder, $i->name, $i->durationMinutes, $i->description, $i->noteGeneral, $i->noteSound, $i->noteLight, $i->noteVideo, $i->noteStage, $i->noStrobe ? 1 : 0]);
        }

        return substr(sha1(implode("\n", array_map(static fn ($v): string => (string) $v, $parts))), 0, 16);
    }

    private static function time(?DateTimeImmutable $time): ?string
    {
        return $time?->format(DATE_ATOM);
    }
}
