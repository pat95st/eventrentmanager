<?php

declare(strict_types=1);

namespace App\Application\Federation\Command;

use App\Application\Command\CommandInterface;

/** Accept a request a partner sent us. */
final class AcceptIncomingPartnerRequest implements CommandInterface
{
    public function __construct(
        public readonly string $requestId,
        /** @var string[] */
        public readonly array $technicianIds = [],
        /** @var string[] */
        public readonly array $tradeIds = [],
        public readonly string $message = '',
    ) {
    }
}
