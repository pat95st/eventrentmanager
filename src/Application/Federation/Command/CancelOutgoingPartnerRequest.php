<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Cancel a request we sent to a partner. */
final class CancelOutgoingPartnerRequest implements CommandInterface
{
    public function __construct(
        public readonly string $requestId,
    ) {
    }
}
