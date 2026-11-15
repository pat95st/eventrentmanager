<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Local settings of a partnership (not shared with the partner). */
final class UpdatePartnerSettings implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly bool $autoAcceptRequests,
        public readonly bool $allowTechnicianRequests,
        public readonly ?string $customerId = null,
    ) {
    }
}
