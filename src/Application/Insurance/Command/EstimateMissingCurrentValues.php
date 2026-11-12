<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandInterface;

/**
 * Für alle ausgegebenen Exemplare einer Veranstaltung ohne Zeitwert (weder
 * eigener noch fester Artikel-Wert) den Zeitwert per KI schätzen. Liefert
 * ein EstimationSummary.
 */
final class EstimateMissingCurrentValues implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly int $maxRequests = 60,
    ) {
    }
}
