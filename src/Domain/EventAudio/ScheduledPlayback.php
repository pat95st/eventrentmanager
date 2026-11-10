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
    ) {
    }
}
