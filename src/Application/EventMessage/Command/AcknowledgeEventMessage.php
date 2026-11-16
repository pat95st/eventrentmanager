<?php

declare(strict_types=1);

namespace App\Application\EventMessage\Command;

use App\Application\Command\CommandInterface;

/** FOH confirms having seen a message (migration 167). */
final class AcknowledgeEventMessage implements CommandInterface
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $rentalId,
        public readonly string $byName,
    ) {
    }
}
