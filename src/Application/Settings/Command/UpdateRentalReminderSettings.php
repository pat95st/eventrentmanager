<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateRentalReminderSettings implements CommandInterface
{
    public function __construct(
        public readonly string $pickupLeadHours, // als String, wird im Handler validiert/geparst (siehe UpdateInvoiceSettings::$paymentTermDays für dasselbe Muster)
        public readonly string $returnLeadHours,
        public readonly string $checklistLeadHours,
        public readonly bool $checklistAutoSendEnabled,
    ) {
    }
}
