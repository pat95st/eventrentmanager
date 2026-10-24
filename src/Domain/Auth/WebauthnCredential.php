<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

/**
 * Ein registrierter Passkey (WebAuthn-Credential) eines Benutzers. Der
 * private Schlüssel verlässt nie das Authenticator-Gerät des Benutzers;
 * hier wird ausschließlich der öffentliche Schlüssel im COSE-Format
 * (siehe App\Infrastructure\WebAuthn\WebAuthnService) sowie der Sign-Count
 * (Klon-Erkennung, siehe CompletePasskeyLoginHandler) gespeichert.
 */
final class WebauthnCredential
{
    /**
     * @param array<string, mixed> $publicKeyCose Rohe COSE-Key-Felder (kty, alg,
     *        und je nach Schlüsseltyp crv/x/y (EC2) oder n/e (RSA), jeweils
     *        base64url-kodiert), ausreichend um für die Signaturprüfung ein
     *        PEM zu rekonstruieren (siehe WebAuthnService::coseKeyToPem()).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $credentialId,
        public array $publicKeyCose,
        public int $signCount,
        public ?string $nickname,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
    ) {
    }
}
