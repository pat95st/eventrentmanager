<?php

declare(strict_types=1);

namespace App\Application\Auth\Query;

use App\Domain\Auth\UserSession;

/** Eine Zeile für die Anzeige unter "Aktive Sitzungen", siehe ListSessionsForUserHandler. */
final class SessionListEntry
{
    public function __construct(
        public readonly UserSession $session,
        public readonly bool $isCurrent,
    ) {
    }
}
