<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Fetch the current state of a request we sent to a partner. */
final class RefreshOutgoingPartnerRequest implements CommandInterface
{
    public function __construct(
        public readonly string $requestId,
    ) {
    }
}
