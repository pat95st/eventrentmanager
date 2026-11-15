<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Decline a request a partner sent us. */
final class DeclineIncomingPartnerRequest implements CommandInterface
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $message = '',
    ) {
    }
}
