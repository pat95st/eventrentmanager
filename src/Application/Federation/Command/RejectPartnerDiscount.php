<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Reject the partner's discount proposal or withdraw our own. */
final class RejectPartnerDiscount implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
    ) {
    }
}
