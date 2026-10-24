<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

/**
 * Eine ausgestellte WebAuthn-Challenge (Registrierung oder Anmeldung),
 * analog zu LoginChallenge beim E-Mail-Code-2FA: einmal verwendbar
 * (consumedAt) und kurzlebig (expiresAt), um Replay-Angriffe zu
 * verhindern. Bei der Anmeldung (discoverable/"usernameless" Flow, siehe
 * BeginPasskeyLoginHandler) ist der Benutzer noch nicht bekannt, daher
 * userId hier nullable.
 */
final class WebauthnChallenge
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $userId,
        public readonly string $challenge,
        public readonly WebauthnChallengeType $type,
        public readonly DateTimeImmutable $expiresAt,
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

    public function isUsable(DateTimeImmutable $now): bool
    {
        return !$this->isExpired($now) && !$this->isConsumed();
    }
}
