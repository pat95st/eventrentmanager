<?php

declare(strict_types=1);

namespace App\Application\Insurance\Query;

use App\Application\Query\QueryInterface;

/**
 * Versicherungsliste einer Veranstaltung: alle ausgegebenen Exemplare und —
 * optional — die noch nicht ausgegebenen, nur geplanten Stückzahlen.
 */
final class GetRentalInsuranceList implements QueryInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly bool $includeUnissued = true,
    ) {
    }
}
