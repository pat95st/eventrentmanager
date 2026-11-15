<?php

declare(strict_types=1);

namespace App\Application\EventMessage\Command;

use App\Application\Command\CommandInterface;

/** Send a message to the stage display and/or FOH (migration 166). Returns the new id. */
final class SendEventMessage implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $body,
        public readonly bool $toStage,
        public readonly bool $toFoh,
        public readonly bool $urgent,
        public readonly ?string $minuteProgramId,
        public readonly string $sentByType,
        public readonly ?string $sentById,
        public readonly string $sentByName,
    ) {
    }
}
