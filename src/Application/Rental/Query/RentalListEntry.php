<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;

final class RentalListEntry
{
    public function __construct(
        public readonly Rental $rental,
        public readonly Customer $customer,
    ) {
    }
}
