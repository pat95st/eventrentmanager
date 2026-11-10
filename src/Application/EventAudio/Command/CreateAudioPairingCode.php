<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

/** Neuen 6-stelligen Kopplungs-Code für eine Veranstaltung erzeugen (Migration 141). */
final class CreateAudioPairingCode implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $createdByName,
    ) {
    }
}
