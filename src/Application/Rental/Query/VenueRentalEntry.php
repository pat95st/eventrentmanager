<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;

/** Ein Eintrag in der Veranstaltungsliste einer Veranstaltungsort-Detailseite (vergangen/aktuell/kommend). */
final class VenueRentalEntry
{
    public function __construct(
        public readonly Rental $rental,
        public readonly Customer $customer,
    ) {
    }
}
