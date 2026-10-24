<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

/**
 * Eine ausgestellte E-Mail-OTP-Challenge (zweiter Faktor "Code per E-Mail").
 * Der Code selbst wird nie im Klartext gespeichert, nur sein Hash
 * (password_hash, wie beim Login-Passwort) — analog dazu wird er über
 * password_verify() geprüft, siehe VerifyTwoFactorCodeHandler.
 */
final class LoginChallenge
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public string $codeHash,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $consumedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now > $this->expiresAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }
}
