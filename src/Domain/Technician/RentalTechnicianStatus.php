<?php

declare(strict_types=1);

namespace App\Domain\Technician;

/** Status eines Technikers an einer Veranstaltung (Migration 134). */
enum RentalTechnicianStatus: string
{
    case Requested = 'requested'; // angefragt, Antwort steht aus
    case Confirmed = 'confirmed'; // hat zugesagt
    case Declined = 'declined';   // hat abgesagt
    case Assigned = 'assigned';   // direkt verplant (keine Zustimmung nötig)

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Angefragt',
            self::Confirmed => 'Zugesagt',
            self::Declined => 'Abgesagt',
            self::Assigned => 'Verplant',
        };
    }

    /** Gebucht oder angefragt – Techniker sieht die Veranstaltung. */
    public function grantsAccess(): bool
    {
        return $this !== self::Declined;
    }

    /** Fest eingeplant (zugesagt oder direkt verplant). */
    public function isBooked(): bool
    {
        return $this === self::Confirmed || $this === self::Assigned;
    }

    /** CSS-Farben für das Status-Badge (Inline-Styles, unabhängig vom kompilierten Tailwind). */
    public function badgeStyle(): string
    {
        return match ($this) {
            self::Requested => 'background:#fef3c7;color:#92400e',
            self::Confirmed, self::Assigned => 'background:#d1fae5;color:#065f46',
            self::Declined => 'background:#fee2e2;color:#991b1b',
        };
    }
}
