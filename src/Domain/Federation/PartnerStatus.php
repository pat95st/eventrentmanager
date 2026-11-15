<?php

declare(strict_types=1);

namespace App\Domain\Federation;

/** Lifecycle of a partnership with another installation (migration 157). */
enum PartnerStatus: string
{
    // We entered the partner's invitation link and wait for the partner to confirm.
    case AwaitingPartner = 'awaiting_partner';
    // The partner entered our invitation link; we have to confirm.
    case PendingConfirmation = 'pending_confirmation';
    case Active = 'active';
    case Declined = 'declined';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPartner => 'Wartet auf Bestätigung des Partners',
            self::PendingConfirmation => 'Bestätigung erforderlich',
            self::Active => 'Aktiv',
            self::Declined => 'Abgelehnt',
            self::Ended => 'Beendet',
        };
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::AwaitingPartner, self::PendingConfirmation => 'background:#fef3c7;color:#92400e',
            self::Active => 'background:#d1fae5;color:#065f46',
            self::Declined, self::Ended => 'background:#e2e8f0;color:#334155',
        };
    }
}
