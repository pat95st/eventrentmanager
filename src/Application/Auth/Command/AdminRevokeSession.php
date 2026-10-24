<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/**
 * Beendet eine einzelne Sitzung EINES BELIEBIGEN Benutzers durch einen
 * Administrator (siehe UserController::revokeSession()) — anders als
 * RevokeSession (Selbstbedienung im eigenen Profil) prüft der zugehörige
 * Handler die Zugehörigkeit nicht gegen den anfragenden Benutzer, sondern
 * gegen $targetUserId (die in der URL referenzierte Benutzer-ID), damit die
 * Sitzungs-ID aus der URL nicht versehentlich/böswillig eine Sitzung eines
 * ANDEREN als des gerade in der Admin-Oberfläche angezeigten Benutzers
 * beendet.
 */
final class AdminRevokeSession implements CommandInterface
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $targetUserId,
    ) {
    }
}
