<?php

declare(strict_types=1);

namespace App\Domain\Federation;

enum PartnerRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Offen',
            self::Accepted => 'Zugesagt',
            self::Declined => 'Abgelehnt',
            self::Cancelled => 'Storniert',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::Pending => 'background:#fef3c7;color:#92400e',
            self::Accepted => 'background:#d1fae5;color:#065f46',
            self::Declined => 'background:#fee2e2;color:#991b1b',
            self::Cancelled => 'background:#e2e8f0;color:#334155',
        };
    }
}
