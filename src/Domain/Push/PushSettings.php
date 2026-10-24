<?php

declare(strict_types=1);

namespace App\Domain\Push;

/**
 * Anwendungsweite VAPID-Konfiguration (RFC 8292) für den Web-Push-Versand
 * (siehe App\Infrastructure\Push\WebPushSender) — ein einziges Schlüsselpaar
 * für die gesamte Anwendung, nicht pro Benutzer, gespeichert in der
 * Singleton-Tabelle push_settings (id = 1, siehe Migration 043).
 */
final class PushSettings
{
    public function __construct(
        // Unkomprimierter EC-P256-Public-Key (0x04 || X(32) || Y(32)),
        // Base64URL-kodiert — wird dem Browser als applicationServerKey für
        // pushManager.subscribe() mitgegeben und beim Versand im
        // "Authorization: vapid t=…, k=…"-Header übermittelt.
        public ?string $vapidPublicKey,
        // PEM-kodierter EC-Private-Key, ausschließlich serverseitig zum
        // Signieren der VAPID-JWTs verwendet (siehe VapidTokenBuilder).
        public ?string $vapidPrivateKeyPem,
        // JWT-"sub"-Claim, z. B. "mailto:admin@beispiel.de" — vom
        // Push-Dienst genutzt, um den Betreiber bei Missbrauch kontaktieren
        // zu können (siehe RFC 8292).
        public ?string $subjectEmail,
    ) {
    }

    /** Ob bereits ein VAPID-Schlüsselpaar erzeugt wurde (siehe EnsureVapidKeysGeneratedHandler). */
    public function hasKeys(): bool
    {
        return $this->vapidPublicKey !== null && $this->vapidPrivateKeyPem !== null;
    }

    /** Ob alles Nötige für einen tatsächlichen Push-Versand vorhanden ist. */
    public function isConfigured(): bool
    {
        return $this->hasKeys() && $this->subjectEmail !== null && trim($this->subjectEmail) !== '';
    }
}
