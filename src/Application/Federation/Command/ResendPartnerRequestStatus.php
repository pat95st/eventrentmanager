<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Send the state of an incoming request to the requesting partner again. */
final class ResendPartnerRequestStatus implements CommandInterface
{
    public function __construct(
        public readonly string $requestId,
    ) {
    }
}
