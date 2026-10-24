<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Infrastructure\Http\UserAgentParser;
use DateTimeImmutable;

/**
 * Serverseitiger Nachweis einer aktiven Anmelde-Sitzung. id ist bewusst die
 * PHP-Session-ID selbst (siehe App\Infrastructure\Http\Auth\Session), kein
 * separater UUID — dadurch lässt sich beim nächsten Request derselben
 * Session-ID direkt prüfen, ob sie noch als "aktiv" gilt (siehe
 * App\Infrastructure\Http\Auth\AuthGuard::guard()).
 */
final class UserSession
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly ?string $userAgent,
        public readonly ?string $ipAddress,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $lastSeenAt,
    ) {
    }

    /** Best-effort Geräte-/Browser-Beschreibung für die Anzeige, siehe UserAgentParser. */
    public function deviceLabel(): string
    {
        return UserAgentParser::describe($this->userAgent);
    }
}
