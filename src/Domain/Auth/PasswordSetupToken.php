<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

/**
 * Ein ausgestelltes, kurzlebiges Token zum (Erst-)Festlegen eines Passworts
 * — analog zu WebauthnChallenge: einmal verwendbar (usedAt) und kurzlebig
 * (expiresAt), um Replay-Angriffe zu verhindern. Wird für zwei fachlich
 * identische Fälle genutzt (siehe App\Application\Auth\
 * PasswordSetupTokenIssuer): die Admin-Einladung eines Benutzers ohne
 * Erstpasswort, sowie "Passwort vergessen" — beide münden im selben
 * "Passwort festlegen"-Formular. Es wird bewusst nur der Hash des Tokens
 * gehalten (tokenHash, sha256 des zufälligen Klartext-Tokens), nie der
 * Klartext-Token selbst — analog zum Passwort-Hashing-Prinzip, auch wenn
 * hier ein simpler Hash genügt, da das Token bereits hochentropisch
 * (32 zufällige Bytes) statt eines vom Menschen gewählten Geheimnisses ist.
 */
final class PasswordSetupToken
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $tokenHash,
        public readonly DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now > $this->expiresAt;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return !$this->isExpired($now) && !$this->isUsed();
    }
}
