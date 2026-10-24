<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/**
 * Zweiter Schritt der Passkey-Registrierung: verifiziert die Antwort des
 * Browsers (navigator.credentials.create()) und speichert den neuen
 * Passkey. Alle Byte-Felder kommen base64url-kodiert vom Client (siehe
 * PasskeyController::registerComplete() / resources/views/profile/security.php).
 */
final class CompletePasskeyRegistration implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly string $challengeId,
        public readonly string $clientDataJsonB64,
        public readonly string $attestationObjectB64,
        public readonly string $expectedOrigin,
        public readonly ?string $nickname,
    ) {
    }
}
