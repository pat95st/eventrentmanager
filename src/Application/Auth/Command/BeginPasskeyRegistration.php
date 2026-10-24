<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/**
 * Erster Schritt der Passkey-Registrierung: erzeugt die
 * PublicKeyCredentialCreationOptions für navigator.credentials.create()
 * (siehe PasskeyController::registerOptions()). Der Benutzer ist bereits
 * per Passwort angemeldet — rpId/rpOrigin stammen aus dem aktuellen
 * Request (siehe PasskeyController), nicht hart kodiert.
 */
final class BeginPasskeyRegistration implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly string $rpId,
        public readonly string $rpName,
    ) {
    }
}
