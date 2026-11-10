<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

interface ScheduledPlaybackRepositoryInterface
{
    public function save(ScheduledPlayback $schedule): void;

    public function findById(string $id): ?ScheduledPlayback;

    /** @return ScheduledPlayback[] geplante (pending) zuerst nach Zeit, danach die letzten erledigten */
    public function findForRental(string $rentalId, int $recentLimit = 10): array;

    /** @return ScheduledPlayback[] fällige (pending, play_at <= $now), älteste zuerst */
    public function findDue(string $rentalId, DateTimeImmutable $now): array;

    /**
     * Setzt den Status atomar von pending auf $status (verhindert doppeltes
     * Auslösen bei gleichzeitigen Abrufen). true = dieser Aufruf hat gewonnen.
     */
    public function claim(string $id, ScheduledPlaybackStatus $status, DateTimeImmutable $at): bool;

    public function attachRequest(string $id, string $playbackRequestId): void;
}
