<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/** Trägt eine konkrete Inventarnummer als "tatsächlich ausgegeben" in die Ausgabe-Checkliste ein. */
final class IssueRentalItem implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $inventoryNumberId,
    ) {
    }
}
