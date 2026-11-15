<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Request material from a partner's pool. */
final class SendPartnerMaterialRequest implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly ?string $rentalId,
        public readonly string $eventName,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $eventLocation,
        public readonly string $note,
        /** @var array<string, string> article id => quantity */
        public readonly array $quantities,
        public readonly ?string $contactName = null,
        public readonly ?string $contactEmail = null,
    ) {
    }
}
