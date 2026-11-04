<?php

declare(strict_types=1);

namespace App\Domain\Banking;

/** Art einer Kontobuchung (Migration 131). */
enum AccountTransactionKind: string
{
    case Manual = 'manual';   // Eingang/Ausgang ohne Beleg, z. B. Kontogebühren
    case Receipt = 'receipt'; // abgebuchter Beleg
    case Invoice = 'invoice'; // Zahlungseingang zu einer Rechnung
    case Import = 'import';   // aus dem Zahlungsabgleich übernommener Umsatz

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Ohne Beleg',
            self::Receipt => 'Beleg',
            self::Invoice => 'Rechnung',
            self::Import => 'Kontoauszug',
        };
    }
}
