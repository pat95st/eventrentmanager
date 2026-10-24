<?php

declare(strict_types=1);

namespace App\Application\Rma\Query;

use App\Domain\Article\Article;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Rma\Rma;

/**
 * Ein RMA-Vorgang inkl. der aufgelösten Inventurnummer/Artikel/Hersteller-
 * Daten, für die artikelübergreifende RMA-Übersicht.
 */
final class RmaOverviewEntry
{
    public function __construct(
        public readonly Rma $rma,
        public readonly InventoryNumber $inventoryNumber,
        public readonly Article $article,
        public readonly Manufacturer $manufacturer,
        public readonly int $documentCount,
    ) {
    }
}
