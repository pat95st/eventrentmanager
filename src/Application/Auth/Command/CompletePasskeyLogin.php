<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/**
 * Zweiter Schritt der Passkey-Anmeldung: verifiziert die Antwort des
 * Browsers (navigator.credentials.get()) gegen den gespeicherten
 * öffentlichen Schlüssel und liefert bei Erfolg den zugehörigen Benutzer.
 * Ein Passkey ist ein vollwertiger, eigenständiger Anmeldeweg — es gibt
 * hier absichtlich KEINEN Passwort-Fallback und keinen "einfach
 * durchlassen"-Pfad, wenn irgendein Prüfschritt fehlschlägt.
 */
final class CompletePasskeyLogin implements CommandInterface
{
    public function __construct(
        public readonly string $challengeId,
        public readonly string $credentialIdB64,
        public readonly string $clientDataJsonB64,
        public readonly string $authenticatorDataB64,
        public readonly string $signatureB64,
        public readonly string $expectedOrigin,
    ) {
    }
}
