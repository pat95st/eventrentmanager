<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Gewerk löschen (Zuordnungen an Veranstaltungen entfallen). */
final class DeleteTrade implements CommandInterface
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
