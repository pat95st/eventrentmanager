<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Infrastructure\Http\UserAgentParser;
use DateTimeImmutable;

/**
 * Ein Gerät (Browser), dem ein Benutzer beim Login für eine begrenzte Zeit
 * vertraut ("Dem Gerät für die nächsten 10 Tage vertrauen", Migration 143).
 * Solange der Eintrag gültig ist, entfällt auf diesem Gerät die Abfrage des
 * zweiten Faktors. Vom geheimen Teil des Cookies wird nur der SHA-256-Hash
 * gespeichert (tokenHash), siehe App\Application\Auth\TrustedDeviceService.
 */
final class TrustedDevice
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $selector,
        public readonly string $tokenHash,
        public readonly ?string $userAgent,
        public readonly ?string $ipAddress,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    /** Best-effort Geräte-/Browser-Beschreibung für die Anzeige, siehe UserAgentParser. */
    public function deviceLabel(): string
    {
        return UserAgentParser::describe($this->userAgent);
    }
}
