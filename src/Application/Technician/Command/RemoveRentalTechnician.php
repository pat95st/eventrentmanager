<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Techniker von einer Veranstaltung entfernen. */
final class RemoveRentalTechnician implements CommandInterface
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
