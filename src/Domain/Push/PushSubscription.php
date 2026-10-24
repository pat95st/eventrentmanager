<?php

declare(strict_types=1);

namespace App\Domain\Push;

use DateTimeImmutable;

/**
 * Ein einzelnes, per Browser/Gerät registriertes Push-Abo (Ergebnis von
 * pushManager.subscribe() im Browser, siehe resources/views/profile/display.php
 * und ProfileController::storePushSubscription()). Ein Benutzer kann mehrere
 * gleichzeitig besitzen (mehrere Geräte/Browser).
 */
final class PushSubscription
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        // Vom Push-Dienst vergebene, eindeutige Ziel-URL für den Versand
        // (z. B. https://fcm.googleapis.com/fcm/send/…), siehe
        // App\Infrastructure\Push\WebPushSender::send().
        public string $endpoint,
        // Base64URL-kodierter, unkomprimierter EC-P256-Public-Key des
        // Browsers/Geräts (65 Byte), Teil der Nachrichtenverschlüsselung
        // nach RFC 8291.
        public string $p256dh,
        // Base64URL-kodierter, 16 Byte langer Zufallswert des Browsers/Geräts
        // (HKDF-Salt-Bestandteil, siehe RFC 8291).
        public string $auth,
        public ?string $userAgent,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
