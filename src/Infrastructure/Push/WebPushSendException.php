<?php

declare(strict_types=1);

namespace App\Infrastructure\Push;

use RuntimeException;

/** Netzwerk-/Protokollfehler beim eigentlichen HTTP-POST an einen Push-Dienst, siehe WebPushSender::send(). */
final class WebPushSendException extends RuntimeException
{
}
