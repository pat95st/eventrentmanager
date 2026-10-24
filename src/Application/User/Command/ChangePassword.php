<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

/**
 * Vom Administrator ausgelöstes Zurücksetzen des Passworts eines fremden
 * Benutzers (kein aktuelles Passwort nötig, dafür erzwingt es einen
 * Passwortwechsel beim nächsten Login). Für die Selbstbedienung siehe
 * ChangeOwnPassword.
 */
final class ChangePassword implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly string $newPassword,
    ) {
    }
}
