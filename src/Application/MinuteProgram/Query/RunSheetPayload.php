<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\EventMessage\EventMessage;
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
     * $context['messages']: active messages (migration 169) already filtered
     * for the audience (stage display: only those sent to the stage).
     *
     * @param array{eventName?: ?string, eventNoStrobe?: bool, jingleTitle?: ?string, canControl?: bool, messages?: list<EventMessage>} $context
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

        $messages = $context['messages'] ?? [];

        $state = match (true) {
            $detail->finished => 'finished',
            $current !== null => 'running',
            default => 'pending',
        };

        return [
            'apiVersion' => self::API_VERSION,
            'serverTime' => $now->format(DATE_ATOM),
            // Changes whenever plan or live state change (not with the clock).
            'revision' => self::revision($detail, $context['eventNoStrobe'] ?? false, $messages),
            'event' => [
                'name' => $context['eventName'] ?? null,
                // Migration 166: no strobe allowed at the whole event.
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
            // Migration 172: trades the program offers notes for (with display color).
            'trades' => array_map(static fn (MinuteProgramTrade $t): array => $t->toArray(), $detail->trades),
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
            'current' => $current !== null ? self::itemRef($current, $now, $detail->trades) : null,
            'next' => $next !== null ? self::itemRef($next, $now, $detail->trades) : null,
            'items' => array_map(static fn (MinuteProgramItemDetail $d): array => self::item($d, $detail->trades), $items),
            // Migration 167: messages from the organizer, oldest first.
            'messages' => array_map(static fn (EventMessage $m): array => self::message($m), array_values($messages)),
        ];
    }

    /**
     * @param list<MinuteProgramTrade> $trades
     *
     * @return array<string, mixed>
     */
    private static function itemRef(MinuteProgramItemDetail $d, DateTimeImmutable $now, array $trades): array
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
            'notes' => self::notes($d, $trades),
            'tradeNotes' => self::tradeNotes($d, $trades),
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

    /**
     * @param list<MinuteProgramTrade> $trades
     *
     * @return array<string, mixed>
     */
    private static function item(MinuteProgramItemDetail $d, array $trades): array
    {
        return [
            'id' => $d->item->id,
            'position' => $d->position,
            'name' => $d->item->name,
            'description' => $d->item->description,
            'notes' => self::notes($d, $trades),
            'tradeNotes' => self::tradeNotes($d, $trades),
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

    /**
     * Legacy note keys of API v1 (before migration 172 the notes were fixed
     * columns). sound/light/video/stage are filled from the offered trade
     * whose name matches (see LEGACY_TRADE_NAMES), otherwise null.
     *
     * @param list<MinuteProgramTrade> $trades
     *
     * @return array{general: ?string, sound: ?string, light: ?string, video: ?string, stage: ?string}
     */
    private static function notes(MinuteProgramItemDetail $d, array $trades): array
    {
        $notes = ['general' => $d->item->noteGeneral, 'sound' => null, 'light' => null, 'video' => null, 'stage' => null];
        foreach ($trades as $trade) {
            $key = self::legacyKey($trade->name);
            if ($key !== null && $notes[$key] === null && isset($d->item->tradeNotes[$trade->id])) {
                $notes[$key] = $d->item->tradeNotes[$trade->id];
            }
        }

        return $notes;
    }

    /**
     * Migration 172: non-empty notes of the offered trades, in trade order.
     *
     * @param list<MinuteProgramTrade> $trades
     *
     * @return list<array{tradeId: string, trade: string, color: string, text: string}>
     */
    private static function tradeNotes(MinuteProgramItemDetail $d, array $trades): array
    {
        $result = [];
        foreach ($trades as $trade) {
            if (isset($d->item->tradeNotes[$trade->id])) {
                $result[] = ['tradeId' => $trade->id, 'trade' => $trade->name, 'color' => $trade->color, 'text' => $d->item->tradeNotes[$trade->id]];
            }
        }

        return $result;
    }

    /** Trade names mapped to the legacy note keys of API v1. */
    private const LEGACY_TRADE_NAMES = [
        'sound' => ['ton', 'tontechnik', 'audio', 'sound'],
        'light' => ['licht', 'lichttechnik', 'light', 'lighting'],
        'video' => ['video', 'videotechnik'],
        'stage' => ['bühne', 'buehne', 'bühnentechnik', 'stage'],
    ];

    private static function legacyKey(string $tradeName): ?string
    {
        $name = mb_strtolower(trim($tradeName));
        foreach (self::LEGACY_TRADE_NAMES as $key => $names) {
            if (in_array($name, $names, true)) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function message(EventMessage $m): array
    {
        return [
            'id' => $m->id,
            'text' => $m->body,
            'urgent' => $m->urgent,
            'stage' => $m->toStage,
            'foh' => $m->toFoh,
            'allPrograms' => $m->minuteProgramId === null,
            'sentAt' => self::time($m->createdAt),
            'sentBy' => $m->sentByName,
            'acknowledgedAt' => self::time($m->acknowledgedAt),
            'acknowledgedBy' => $m->acknowledgedBy,
        ];
    }

    /** @param list<EventMessage> $messages */
    private static function revision(MinuteProgramDetail $detail, bool $eventNoStrobe, array $messages = []): string
    {
        $p = $detail->program;
        $parts = [$p->updatedAt->format(DATE_ATOM), $p->startAt->format(DATE_ATOM), $p->currentDelaySeconds, $p->liveItemId, $p->liveFinishedAt?->format(DATE_ATOM), $eventNoStrobe ? 1 : 0];
        foreach ($detail->items as $d) {
            $i = $d->item;
            $parts[] = implode('|', [$i->id, $i->sortOrder, $i->name, $i->durationMinutes, $i->description, $i->noteGeneral, json_encode($i->tradeNotes), $i->noStrobe ? 1 : 0]);
        }
        $parts[] = 't|' . implode(',', array_map(static fn (MinuteProgramTrade $t): string => $t->id . ':' . $t->name, $detail->trades));
        foreach ($messages as $m) {
            $parts[] = 'm|' . $m->id . '|' . ($m->acknowledgedAt?->format(DATE_ATOM) ?? '');
        }

        return substr(sha1(implode("\n", array_map(static fn ($v): string => (string) $v, $parts))), 0, 16);
    }

    private static function time(?DateTimeImmutable $time): ?string
    {
        return $time?->format(DATE_ATOM);
    }
}
