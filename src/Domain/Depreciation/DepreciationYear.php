<?php

declare(strict_types=1);

namespace App\Domain\Depreciation;

/** Ein Jahr eines Abschreibungsplans (siehe DepreciationSchedule). Beträge in Euro. */
final class DepreciationYear
{
    public function __construct(
        public readonly int $year,
        public readonly float $amount,       // Abschreibung (AfA) in diesem Jahr
        public readonly float $disposal,     // Restbuchwert, der in diesem Jahr durch Verkauf ausgebucht wird
        public readonly float $bookValueEnd, // Restbuchwert am Jahresende
    ) {
    }
}
