<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandInterface;

/** Soft-deletes a trip (migration 184). */
final class DeleteLogbookEntry implements CommandInterface
{
    public function __construct(
        public readonly string $entryId,
        public readonly string $actorUserId,
    ) {
    }
}
