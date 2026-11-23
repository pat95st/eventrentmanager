<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandInterface;

/** Approves or rejects a trip (migration 185, logbook managers only). */
final class ReviewLogbookEntry implements CommandInterface
{
    public function __construct(
        public readonly string $entryId,
        public readonly string $actorUserId,
        public readonly bool $approve,
    ) {
    }
}
