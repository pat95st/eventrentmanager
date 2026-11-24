<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

/**
 * Approval state of a trip (migration 184). Pending and rejected trips do not
 * count for billing, but still for the odometer chain.
 */
enum LogbookStatus: string
{
    case Approved = 'approved';
    case Pending = 'pending';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Freigegeben',
            self::Pending => 'Ungeprüft',
            self::Rejected => 'Abgelehnt',
        };
    }
}
