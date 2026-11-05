<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Erinnerungszeitpunkte für unbeantwortete Anfragen, z. B. "24, 72". */
final class SaveTechnicianSettings implements CommandInterface
{
    public function __construct(
        public readonly string $reminderHours,
    ) {
    }
}
