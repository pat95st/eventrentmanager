<?php

declare(strict_types=1);

namespace App\Application\Statistics;

use App\Domain\User\Permission;

/** Die Statistiken auf dem Dashboard; der Wert ist zugleich der URL-Teil von /statistiken/{key}. */
enum StatisticKey: string
{
    case Revenue = 'einnahmen';
    case Rentals = 'veranstaltungen';
    case TopArticles = 'top-artikel';
    case Stock = 'bestand';
    case Offers = 'angebote';
    case Inspections = 'elektropruefungen';

    public function label(): string
    {
        return match ($this) {
            self::Revenue => 'Einnahmen & Ausgaben',
            self::Rentals => 'Veranstaltungen pro Monat',
            self::TopArticles => 'Meistvermietete Artikel',
            self::Stock => 'Bestand nach Kategorie',
            self::Offers => 'Angebote nach Status',
            self::Inspections => 'Elektroprüfungen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Revenue => 'Bezahlte Rechnungen (inkl. Korrekturen) und Belege je Monat, im Vergleich zum Vorjahr.',
            self::Rentals => 'Anzahl der Veranstaltungen nach Beginn – zurückliegende Monate und die kommenden drei Monate.',
            self::TopArticles => 'Artikel nach vermieteter Stückzahl in Veranstaltungen des gewählten Zeitraums.',
            self::Stock => 'Exemplare im Bestand (ohne verkaufte) je Artikelkategorie.',
            self::Offers => 'Status der im gewählten Zeitraum angelegten Angebote.',
            self::Inspections => 'Prüfpflichtige Geräte-Exemplare nach Stand der letzten Elektroprüfung.',
        };
    }

    public function requiredPermission(): Permission
    {
        return match ($this) {
            self::Revenue => Permission::Administration,
            self::Rentals, self::Offers => Permission::AusgabeRuecknahme,
            self::TopArticles, self::Stock, self::Inspections => Permission::Artikelverwaltung,
        };
    }

    /** Hat der Zeitraum (Monate) Einfluss auf diese Statistik? */
    public function usesPeriod(): bool
    {
        return in_array($this, [self::Rentals, self::TopArticles, self::Offers], true);
    }
}
