<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

/** Einspieler zeitlich planen (Migration 136): vorhandene Datei oder neuer Upload, ein oder mehrere Zeitpunkte. */
final class SchedulePlayback implements CommandInterface
{
    /**
     * @param ?array<string, mixed> $uploadedFile eigene Audio-Datei (statt $audioFileId)
     * @param list<string> $playAts ein oder mehrere Zeitpunkte (Y-m-d\TH:i); je Zeitpunkt ein Eintrag
     */
    public function __construct(
        public readonly string $rentalId,
        public readonly ?string $audioFileId,
        public readonly ?array $uploadedFile,
        public readonly string $uploadTitle,
        public readonly array $playAts,
        public readonly ?string $note,
        public readonly string $createdByType,
        public readonly ?string $createdById,
        public readonly string $createdByName,
    ) {
    }
}
