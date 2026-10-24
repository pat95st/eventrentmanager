<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;

/** Ein Eintrag in der Vermietungs-Historie einer einzelnen Inventurnummer. */
final class RentalHistoryEntry
{
    public function __construct(
        public readonly Rental $rental,
        public readonly Customer $customer,
        public readonly \DateTimeImmutable $issuedAt,
    ) {
    }
}
