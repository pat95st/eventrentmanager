<?php

declare(strict_types=1);

namespace App\Domain\Receipt;

/**
 * Rein interne Buchhaltungs-Markierung, ob ein Beleg bereits bezahlt wurde —
 * anders als InvoiceStatus KEIN einseitig gerichteter Workflow: der Status
 * kann jederzeit frei in beide Richtungen umgeschaltet werden (siehe
 * MarkReceiptPaidHandler/MarkReceiptOpenHandler).
 */
enum ReceiptStatus: string
{
    case Open = 'offen';
    case Paid = 'bezahlt';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Paid => 'Bezahlt',
        };
    }

    /** Tailwind-Badge-Klassen für die farbliche Kennzeichnung in Listen/Detailansicht. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Paid => 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400',
        };
    }
}
