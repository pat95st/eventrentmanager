<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;

/**
 * Schlankes Lesemodell für die Kalenderansicht: die Vermietung plus die
 * Materialbezeichnungen, die in den Tageszellen angezeigt werden — bereits
 * ausgegebene Inventarnummern, sonst (noch nichts ausgegeben) die geplanten
 * Artikel als Fallback, damit auch bevorstehende Vermietungen sichtbar sind.
 */
final class CalendarRentalEntry
{
    /** @param string[] $materialLabels */
    public function __construct(
        public readonly Rental $rental,
        public readonly Customer $customer,
        public readonly array $materialLabels,
    ) {
    }
}
