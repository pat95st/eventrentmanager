<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/** Bestätigt "Vor-Ausgabe-Checks geprüft" für einen Posten (Modal auf der Ausgabe-Checkliste). */
final class ConfirmRentalItemPreIssueChecks implements CommandInterface
{
    public function __construct(public readonly string $rentalItemId)
    {
    }
}
