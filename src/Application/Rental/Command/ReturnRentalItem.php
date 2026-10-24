<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/** Rückgabe-Checkliste: erfasst die Rückgabe eines ausgegebenen Postens inkl. Funktionstest-Ergebnis. */
final class ReturnRentalItem implements CommandInterface
{
    public function __construct(
        public readonly string $issuedItemId,
        public readonly bool $functionTestPassed,
    ) {
    }
}
