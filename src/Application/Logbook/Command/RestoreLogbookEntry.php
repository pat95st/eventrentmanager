<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandInterface;

/** Restores a deleted trip (migration 184, logbook managers only). */
final class RestoreLogbookEntry implements CommandInterface
{
    public function __construct(
        public readonly string $entryId,
        public readonly string $actorUserId,
    ) {
    }
}
