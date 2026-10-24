<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;

final class RentalCustomerChecklistView
{
    /** @param RentalIssuedItemWithDetails[] $items Nur die AKTUELL ausgegebenen (noch nicht zurückgegebenen) Posten. */
    public function __construct(
        public readonly Rental $rental,
        public readonly Customer $customer,
        public readonly array $items,
    ) {
    }
}
