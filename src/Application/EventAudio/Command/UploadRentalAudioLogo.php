<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

/** Eigenes Logo der Veranstaltung für Veranstalter in der Live-Oberfläche (Migration 137). */
final class UploadRentalAudioLogo implements CommandInterface
{
    /** @param array<string, mixed> $uploadedFile */
    public function __construct(
        public readonly string $rentalId,
        public readonly array $uploadedFile,
    ) {
    }
}
