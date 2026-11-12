<?php

declare(strict_types=1);

namespace App\Domain\Datev;

/**
 * DATEV-Standardkontenrahmen (SKR). Liefert die Vorschlagskonten, die
 * greifen, solange in den DATEV-Einstellungen kein eigenes Konto hinterlegt
 * ist (siehe DatevSettings::account()). Die Kontonummern sind 4-stellig
 * angegeben und werden bei längeren Sachkonten rechts mit Nullen aufgefüllt
 * (DATEV-Konvention, siehe DatevSettings::padAccount()).
 *
 * Erlöskonten 19 %/7 % sind in beiden Kontenrahmen Automatikkonten — DATEV
 * rechnet die Umsatzsteuer aus dem Bruttobetrag selbst heraus, ein
 * BU-Schlüssel ist dafür nicht nötig.
 */
enum DatevChartOfAccounts: string
{
    case Skr03 = '03';
    case Skr04 = '04';

    public function label(): string
    {
        return match ($this) {
            self::Skr03 => 'SKR 03',
            self::Skr04 => 'SKR 04',
        };
    }

    /** Vorschlagskonto (4-stellig) für eine Kontenrolle. */
    public function defaultAccount(DatevAccountRole $role): int
    {
        return match ($this) {
            self::Skr03 => match ($role) {
                DatevAccountRole::Revenue19 => 8400,
                DatevAccountRole::Revenue7 => 8300,
                DatevAccountRole::RevenueTaxFree => 8100,
                DatevAccountRole::RevenueSmallBusiness => 8195,
                DatevAccountRole::ExpenseGoods => 3200,
                DatevAccountRole::ExpenseTools => 4985,
                DatevAccountRole::ExpenseVehicle => 4530,
                DatevAccountRole::ExpenseOther => 4900,
            },
            self::Skr04 => match ($role) {
                DatevAccountRole::Revenue19 => 4400,
                DatevAccountRole::Revenue7 => 4300,
                DatevAccountRole::RevenueTaxFree => 4100,
                DatevAccountRole::RevenueSmallBusiness => 4185,
                DatevAccountRole::ExpenseGoods => 5200,
                DatevAccountRole::ExpenseTools => 6845,
                DatevAccountRole::ExpenseVehicle => 6530,
                DatevAccountRole::ExpenseOther => 6300,
            },
        };
    }
}
