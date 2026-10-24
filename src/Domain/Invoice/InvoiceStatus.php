<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

/**
 * Status-Workflow einer Rechnung: Entwurf -> Versendet -> Bezahlt, plus
 * Storniert (erreichbar aus Entwurf oder Versendet, NICHT aus Bezahlt). Der
 * Übergang erfolgt ausschließlich über explizite Benutzeraktionen (siehe
 * MarkInvoiceSent-/Paid-/CancelledHandler). Anders als bei Angeboten hat der
 * Übergang Entwurf -> Versendet hier eine fachlich einschneidende
 * Nebenwirkung: erst dabei wird die gesetzlich relevante Rechnungsnummer
 * vergeben (siehe MarkInvoiceSentHandler, Invoice::isFinalized()).
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Versendet',
            self::Paid => 'Bezahlt',
            self::Cancelled => 'Storniert',
        };
    }

    /** Tailwind-Badge-Klassen für die farbliche Kennzeichnung in Listen/Detailansicht. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Sent => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
            self::Paid => 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400',
            self::Cancelled => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400',
        };
    }
}
