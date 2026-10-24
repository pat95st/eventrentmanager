<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/** Beendet ALLE Sitzungen eines Benutzers auf einmal (Admin-Aktion, z. B. bei einem ausgeschiedenen Mitarbeiter oder Verdacht auf Kontoübernahme). */
final class AdminRevokeAllSessions implements CommandInterface
{
    public function __construct(public readonly string $userId)
    {
    }
}
