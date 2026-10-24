<?php

declare(strict_types=1);

namespace App\Domain\Offer;

/**
 * Status-Workflow eines Angebots: Entwurf -> Versendet -> (Angenommen |
 * Abgelehnt | Abgelaufen). Der Übergang erfolgt ausschließlich über
 * explizite Benutzeraktionen (siehe MarkOfferSent-/Accepted-/DeclinedHandler
 * für die erlaubten Übergänge); "Abgelaufen" wird NICHT automatisch
 * gesetzt, sondern nur für die Anzeige aus validUntil abgeleitet (siehe
 * Offer::isExpiredForDisplay()).
 */
enum OfferStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Versendet',
            self::Accepted => 'Angenommen',
            self::Declined => 'Abgelehnt',
            self::Expired => 'Abgelaufen',
        };
    }

    /** Tailwind-Badge-Klassen für die farbliche Kennzeichnung in Listen/Detailansicht. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Sent => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
            self::Accepted => 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400',
            self::Declined => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400',
            self::Expired => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
        };
    }
}
