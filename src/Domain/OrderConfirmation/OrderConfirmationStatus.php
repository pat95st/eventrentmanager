<?php

declare(strict_types=1);

namespace App\Domain\OrderConfirmation;

/**
 * Status-Workflow einer Auftragsbestätigung: Entwurf -> Versendet ->
 * Bestätigt, sowie Storniert (nur aus Entwurf oder Versendet) — analog
 * InvoiceStatus, bewusst einfacher als OfferStatus (kein "Abgelaufen", da
 * eine Auftragsbestätigung kein validUntil besitzt).
 */
enum OrderConfirmationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Versendet',
            self::Confirmed => 'Bestätigt',
            self::Cancelled => 'Storniert',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Sent => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
            self::Confirmed => 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400',
            self::Cancelled => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400',
        };
    }
}
