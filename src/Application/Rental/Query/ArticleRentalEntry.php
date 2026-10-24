<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalItem;

/** Ein Eintrag in der Vermietungsliste einer Artikel-Detailseite (vergangen/aktuell/kommend). */
final class ArticleRentalEntry
{
    public function __construct(
        public readonly Rental $rental,
        public readonly Customer $customer,
        public readonly RentalItem $item,
        public readonly int $issuedCount,
    ) {
    }
}
