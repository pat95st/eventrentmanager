<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Query\QueryInterface;

/**
 * Für die Warnbadge in der Veranstaltungsort-Liste (venues/_table.php):
 * liefert für eine Menge von Veranstaltungsort-IDs (typischerweise die
 * IDs der aktuell angezeigten Seite), ob mindestens einer ihrer
 * Stromanschlüsse einen eigenen Verteiler mit RCD benötigt — in EINER
 * zusätzlichen Abfrage statt pro Zeile einzeln (kein N+1).
 */
final class GetVenuesNeedDistributorFlags implements QueryInterface
{
    /** @param string[] $venueIds */
    public function __construct(public readonly array $venueIds)
    {
    }
}
