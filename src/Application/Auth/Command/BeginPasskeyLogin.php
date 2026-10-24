<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/**
 * Erster Schritt der Passkey-Anmeldung (öffentliche Route, kein Benutzer
 * angemeldet). Discoverable-Credential-Flow: es wird bewusst keine
 * E-Mail-Adresse abgefragt, der Browser zeigt dem Benutzer direkt alle für
 * diese Seite gespeicherten Passkeys zur Auswahl an.
 */
final class BeginPasskeyLogin implements CommandInterface
{
    public function __construct(public readonly string $rpId)
    {
    }
}
