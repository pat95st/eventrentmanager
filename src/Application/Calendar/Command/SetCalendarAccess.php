<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandInterface;

/** Ersetzt die komplette Liste der User mit explizitem Zugriff auf einen Kalender. */
final class SetCalendarAccess implements CommandInterface
{
    /** @param string[] $userIds */
    public function __construct(
        public readonly string $calendarId,
        public readonly array $userIds,
    ) {
    }
}
