<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

/**
 * Abspielauftrag (Warteschlange) für das Abspielgerät einer Veranstaltung:
 * Jingle, Audio-Datei, Text-Durchsage (Sprachausgabe auf dem Pi),
 * Mikrofon-Aufnahme oder Stopp. Der Pi holt offene Aufträge über die
 * Player-API ab und meldet den Status zurück.
 */
final class PlaybackRequest
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly PlaybackKind $kind,
        public readonly ?string $audioFileId,
        public readonly ?string $text,
        public readonly ?string $recordingStoredFilename,
        public readonly ?string $recordingMimeType,
        public readonly bool $withGong,
        public PlaybackStatus $status,
        public readonly string $requestedByType, // 'user' | 'organizer'
        public readonly ?string $requestedById,
        public readonly string $requestedByName,
        public ?string $playerId,
        public ?string $error,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public readonly ?string $label = null, // Anzeigename (z. B. Jingle-Titel) zum Zeitpunkt des Auftrags
    ) {
    }

    public static function recordingDirectory(string $rentalId): string
    {
        return 'veranstaltungs-audio/' . $rentalId . '/durchsagen';
    }
}
