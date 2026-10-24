<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateInvoiceDueReminderSettings implements CommandInterface
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $leadDays, // als String, wird im Handler validiert/geparst (siehe UpdateRentalReminderSettings::$pickupLeadHours für dasselbe Muster)
    ) {
    }
}
