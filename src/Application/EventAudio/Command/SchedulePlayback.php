<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

/** Einspieler zeitlich planen (Migration 136): vorhandene Datei oder neuer Upload. */
final class SchedulePlayback implements CommandInterface
{
    /**
     * @param ?array<string, mixed> $uploadedFile eigene Audio-Datei (statt $audioFileId)
     */
    public function __construct(
        public readonly string $rentalId,
        public readonly ?string $audioFileId,
        public readonly ?array $uploadedFile,
        public readonly string $uploadTitle,
        public readonly string $playAt,
        public readonly ?string $note,
        public readonly string $createdByType,
        public readonly ?string $createdById,
        public readonly string $createdByName,
    ) {
    }
}
