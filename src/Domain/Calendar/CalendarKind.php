<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

/**
 * Zwei getrennte Kalender-Konzepte (bewusste Entscheidung, siehe
 * CalendarRepositoryInterface):
 * - Event: der eine, fest eingebaute "Veranstaltungskalender" — zeigt ALLE
 *   Vermietungen (Event-Name/-Ort/-Zeitraum), nicht filterbar, nicht löschbar.
 * - Rental: beliebig viele vom Administrator frei anlegbare
 *   "Vermietungskalender", jeweils nach Lagerort/Veranstaltungsort/
 *   Kategorie/Hersteller gefiltert (siehe Calendar).
 */
enum CalendarKind: string
{
    case Event = 'event';
    case Rental = 'rental';
}
