<?php

declare(strict_types=1);

namespace App\Application\EventMessage\Command;

use App\Application\Command\CommandInterface;

/** Remove a message from stage display and FOH – withdrawn by the sender or hidden at FOH (migration 166). */
final class CloseEventMessage implements CommandInterface
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $rentalId,
        public readonly string $byName,
    ) {
    }
}
