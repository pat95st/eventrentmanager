<?php

declare(strict_types=1);

namespace App\Domain\LaborTime;

/**
 * Abrechnungseinheit einer Arbeitszeit-Position: entweder nach Stunden
 * (LaborTimeArticle::$hourlyRate) oder nach Tagen (LaborTimeArticle::$dailyRate)
 * abgerechnet. Der zum gewählten Zeitpunkt gültige Satz wird beim Hinzufügen
 * der Position gesnapshotet (siehe InvoiceLaborItem::$unitRatePerHour/
 * OfferLaborItem::$unitRatePerHour — der Feldname blieb aus Kompatibilität
 * zum bestehenden Schema erhalten, trägt bei Tagessätzen aber den Tagessatz).
 */
enum LaborTimeBillingUnit: string
{
    case Hour = 'hour';
    case Day = 'day';

    public function label(): string
    {
        return match ($this) {
            self::Hour => 'Stunde',
            self::Day => 'Tag',
        };
    }

    /** Kurzform für die kompakte Anzeige in Positions-/PDF-Zeilen, z. B. "2,50 Std. à ...". */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Hour => 'Std.',
            self::Day => 'Tg.',
        };
    }
}
