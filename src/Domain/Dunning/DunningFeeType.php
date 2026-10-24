<?php

declare(strict_types=1);

namespace App\Domain\Dunning;

/**
 * Wie sich die Mahngebühr einer Mahnstufe (siehe DunningLevel) berechnet.
 * None bedeutet ausdrücklich "keine Gebühr" (nicht etwa 0,00 € Festbetrag),
 * damit z. B. eine reine "Zahlungserinnerung" ohne jeden Gebührenhinweis im
 * PDF erscheint, statt mit "Mahngebühr: 0,00 €".
 */
enum DunningFeeType: string
{
    case None = 'none';
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Keine Gebühr',
            self::Fixed => 'Festbetrag',
            self::Percent => 'Prozent vom Rechnungsbetrag',
        };
    }
}
