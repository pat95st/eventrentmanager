<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/** @see \App\Application\Rental\RentalCustomerChecklistTokenIssuer::issue() für die Bedeutung von $forceNew. */
final class IssueRentalCustomerChecklistToken implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly bool $forceNew = false,
    ) {
    }
}
