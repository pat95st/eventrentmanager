<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandInterface;

/** Geräte-Exemplar ab HE $startUnit (von oben, ab 1) in ein Rack einbauen. */
final class AddRackSlot implements CommandInterface
{
    public function __construct(
        public readonly string $rackId,
        public readonly string $inventoryNumberId,
        public readonly ?string $startUnit,
    ) {
    }
}
