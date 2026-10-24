<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use RuntimeException;

/** Versand über einen Messenger ist fehlgeschlagen (falsche Zugangsdaten, abgelehnte Vorlage, Verbindungsfehler, …). */
final class MessageSenderException extends RuntimeException
{
}
