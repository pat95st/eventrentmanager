<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

/**
 * Zeitlich geplanter Einspieler (Jingle oder Audio-Datei) einer Veranstaltung
 * (Migration 136). Zur Startzeit wird daraus ein normaler PlaybackRequest.
 */
final class ScheduledPlayback
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $audioFileId,
        public DateTimeImmutable $playAt,
        public ?string $note,
        public ScheduledPlaybackStatus $status,
        public ?string $playbackRequestId,
        public readonly string $createdByType, // 'user' | 'organizer'
        public readonly ?string $createdById,
        public readonly string $createdByName,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $triggeredAt = null,
        // Migration 139: Wiederholung (Serie)
        public ?int $repeatIntervalMinutes = null,
        public ?DateTimeImmutable $repeatUntil = null,
        public ?int $repeatMax = null,
        public int $repeatCount = 0,
    ) {
    }

    public function isRecurring(): bool
    {
        return $this->repeatIntervalMinutes !== null && $this->repeatIntervalMinutes > 0;
    }

    /** Kurzbeschreibung der Wiederholung, z. B. „alle 10 min · bis 22:00 · 3/10“. */
    public function recurrenceLabel(): ?string
    {
        if (!$this->isRecurring()) {
            return null;
        }
        $m = (int) $this->repeatIntervalMinutes;
        $parts = [$m % 60 === 0 ? ($m === 60 ? 'jede Stunde' : 'alle ' . intdiv($m, 60) . ' Std.') : 'alle ' . $m . ' min'];
        if ($this->repeatUntil !== null) {
            $parts[] = 'bis ' . $this->repeatUntil->format($this->repeatUntil->format('Y-m-d') === $this->playAt->format('Y-m-d') ? 'H:i' : 'd.m. H:i');
        }
        $parts[] = $this->repeatMax !== null ? $this->repeatCount . '/' . $this->repeatMax . ' gespielt' : $this->repeatCount . '× gespielt';

        return implode(' · ', $parts);
    }

    /**
     * Nächster Termin nach $after (strikt später) – oder null, wenn die Serie
     * danach endet (Endzeitpunkt bzw. Anzahl erreicht).
     */
    public function nextOccurrenceAfter(DateTimeImmutable $after, int $playedCount): ?DateTimeImmutable
    {
        if (!$this->isRecurring()) {
            return null;
        }
        if ($this->repeatMax !== null && $playedCount >= $this->repeatMax) {
            return null;
        }
        $step = (int) $this->repeatIntervalMinutes * 60;
        $base = $this->playAt->getTimestamp();
        $n = (int) floor(($after->getTimestamp() - $base) / $step) + 1;
        $next = $this->playAt->setTimestamp($base + max(1, $n) * $step);
        if ($this->repeatUntil !== null && $next > $this->repeatUntil) {
            return null;
        }

        return $next;
    }
}
