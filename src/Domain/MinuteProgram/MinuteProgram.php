<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

use DateTimeImmutable;

/**
 * Ablaufplan (Minutenprogramm) einer Veranstaltung (App\Domain\Rental\Rental).
 * Eine Veranstaltung kann mehrere Minutenprogramme haben. Die tatsächliche
 * Uhrzeit der einzelnen Programmpunkte wird NICHT gespeichert, sondern aus
 * $startAt + kumulativer Dauer + $currentDelaySeconds berechnet, siehe
 * App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder.
 */
final class MinuteProgram
{
    public function __construct(
        public readonly string $id,
        public string $rentalId,
        public string $name,
        public ?string $room,
        public DateTimeImmutable $startAt,
        public bool $jingleBefore,
        public int $handheldMicCount,
        public int $headsetMicCount,
        // Live delay in seconds (may be negative = ahead of schedule). Only
        // shifts the displayed/live times, never the original plan
        // ($startAt, item durations). Migration 157 switched from minutes.
        public int $currentDelaySeconds,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        // Migration 135: welcher hinterlegte Jingle der Veranstaltung vor
        // diesem Programm gespielt wird (App\Domain\EventAudio\RentalAudioFile).
        public ?string $jingleAudioFileId = null,
        // Migration 157: item last started at FOH and its actual start time.
        // While set, that item stays "current" until the next one is started.
        public ?string $liveItemId = null,
        public ?DateTimeImmutable $liveItemStartedAt = null,
        // Migration 157: program marked as finished at FOH.
        public ?DateTimeImmutable $liveFinishedAt = null,
    ) {
    }

    /** Delay rounded to whole minutes (for compact displays). */
    public function delayMinutes(): int
    {
        return (int) round($this->currentDelaySeconds / 60);
    }

    public function delayedStartAt(): DateTimeImmutable
    {
        return $this->startAt->modify(sprintf('%+d seconds', $this->currentDelaySeconds));
    }

    public function isTrackedLive(): bool
    {
        return $this->liveItemId !== null && $this->liveItemStartedAt !== null;
    }

    /** Clears all live state (delay, started item, finished marker). */
    public function resetLive(): void
    {
        $this->currentDelaySeconds = 0;
        $this->liveItemId = null;
        $this->liveItemStartedAt = null;
        $this->liveFinishedAt = null;
    }
}
