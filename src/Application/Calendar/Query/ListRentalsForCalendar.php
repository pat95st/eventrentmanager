<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryInterface;
use DateTimeImmutable;

/**
 * Zentraler Use Case für die Vermietungen EINES Kalenders, inkl. optionalem
 * Zeitraum-Filter (für CalDAV time-range-Anfragen). Löst die Filterkriterien
 * des Kalenders (siehe Calendar) in eine tatsächliche Vermietungs-Suche auf —
 * genutzt vom CalDavController (REPORT/GET-Endpunkte).
 */
final class ListRentalsForCalendar implements QueryInterface
{
    public function __construct(
        public readonly string $calendarId,
        public readonly ?DateTimeImmutable $rangeStart = null,
        public readonly ?DateTimeImmutable $rangeEnd = null,
    ) {
    }
}
