<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/** Kunden-Checkliste: ein Posten wird vom Kunden selbst als "vorhanden" (de)markiert. */
final class ConfirmRentalItemByCustomer implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $rentalIssuedItemId,
        public readonly bool $confirmed,
    ) {
    }
}
