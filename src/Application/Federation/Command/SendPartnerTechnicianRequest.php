<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Request technicians from a partner. */
final class SendPartnerTechnicianRequest implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly ?string $rentalId,
        public readonly string $eventName,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $eventLocation,
        public readonly string $note,
        /** @var string[] trade names */
        public readonly array $trades,
        public readonly string $technicianCount,
        public readonly string $preferredTechnicianId,
        public readonly string $preferredTechnicianName = '',
        public readonly ?string $contactName = null,
        public readonly ?string $contactEmail = null,
    ) {
    }
}
