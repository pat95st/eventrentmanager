<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Accept the partner's pending discount proposal. */
final class AcceptPartnerDiscount implements CommandInterface
{
    public function __construct(
        public readonly string $partnerId,
    ) {
    }
}
