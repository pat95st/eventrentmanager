<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

/** Audio-Einstellungen einer Veranstaltung: zugeordnetes Abspielgerät, Gong-Vorgabe. */
final class RentalAudioSettings
{
    public function __construct(
        public readonly string $rentalId,
        public ?string $playerId = null,
        public bool $gongByDefault = true,
        // Migration 137: eigenes Logo für Veranstalter in der Live-Oberfläche
        public ?string $logoFilename = null,
    ) {
    }
}
