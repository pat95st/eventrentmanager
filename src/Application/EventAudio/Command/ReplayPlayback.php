<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

/** Bereits gespielten Auftrag (Jingle, Audio, Durchsage, Aufnahme) erneut einspielen. */
final class ReplayPlayback implements CommandInterface
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $rentalId,
        public readonly string $requestedByType,
        public readonly ?string $requestedById,
        public readonly string $requestedByName,
    ) {
    }
}
