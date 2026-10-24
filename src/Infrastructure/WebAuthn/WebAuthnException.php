<?php

declare(strict_types=1);

namespace App\Infrastructure\WebAuthn;

use RuntimeException;

/**
 * Jeder Fehlschlag bei der WebAuthn-Verifikation (Challenge/Origin/Typ/
 * Signatur ungültig, abgelaufen, bereits verwendet, …) — bewusst eine
 * einzige, generische Exception: welcher der vielen Schritte genau
 * fehlgeschlagen ist, wird nicht an den Client durchgereicht (sonst ließe
 * sich z. B. erraten, welche Credential-ID zu welchem Konto gehört), siehe
 * message() vs. der öffentlichen Fehlermeldung in PasskeyController.
 */
final class WebAuthnException extends RuntimeException
{
}
