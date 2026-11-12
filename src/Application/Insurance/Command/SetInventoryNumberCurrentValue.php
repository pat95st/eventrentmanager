<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandInterface;

/** Zeitwert eines Exemplars händisch setzen; leer/null löscht ihn (Migration 143). */
final class SetInventoryNumberCurrentValue implements CommandInterface
{
    public function __construct(
        public readonly string $inventoryNumberId,
        public readonly ?string $value,
    ) {
    }
}
