<?php

declare(strict_types=1);

namespace App\Infrastructure\Push;

/** Antwort eines Push-Dienstes auf einen erfolgreich durchgeführten HTTP-POST (siehe WebPushSender::send()). */
final class WebPushSendResult
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * HTTP 404/410: der Push-Dienst meldet, dass diese Subscription
     * abgelaufen/ungültig ist (z. B. Browser deinstalliert, Gerät
     * zurückgesetzt) — der Aufrufer soll sie dauerhaft aus der Datenbank
     * entfernen (siehe ImportReceiptEmailsHandler::sendPushNotifications()).
     */
    public function isGone(): bool
    {
        return $this->statusCode === 404 || $this->statusCode === 410;
    }
}
