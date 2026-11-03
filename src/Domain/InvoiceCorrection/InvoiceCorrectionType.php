<?php

declare(strict_types=1);

namespace App\Domain\InvoiceCorrection;

/** Art der Rechnungskorrektur (Migration 121). */
enum InvoiceCorrectionType: string
{
    /** Vollstorno: alle Positionen der Rechnung (und bisheriger Korrekturen) negativ. */
    case Cancellation = 'cancellation';
    /** Teilkorrektur: nur geänderte Positionen bzw. die Differenz. */
    case Partial = 'partial';

    /** Dokumenttitel (Detailseite, PDF). */
    public function label(): string
    {
        return match ($this) {
            self::Cancellation => 'Stornorechnung',
            self::Partial => 'Rechnungskorrektur',
        };
    }
}
