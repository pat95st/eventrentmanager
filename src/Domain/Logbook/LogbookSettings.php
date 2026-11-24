<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

/** Global logbook settings (migration 184, singleton). */
final class LogbookSettings
{
    public const MAX_EDIT_WINDOW_DAYS = 365;

    public function __construct(
        // How long drivers may edit or delete their own trips (days after recording).
        public int $editWindowDays = 7,
    ) {
    }
}
