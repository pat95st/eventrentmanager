<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

/**
 * Protokolliert einen Zeitraum, in dem ein Administrator sich als ein
 * anderer Benutzer ausgegeben hat ("Impersonation"). Dient ausschließlich
 * der Nachvollziehbarkeit (Audit-Log, siehe users/impersonations.php) — die
 * eigentliche "Wer bin ich aktuell"-Information während einer laufenden
 * Impersonation liegt in der PHP-Session selbst
 * (App\Infrastructure\Http\Auth\Session::startImpersonation()).
 */
final class Impersonation
{
    public function __construct(
        public readonly string $id,
        public readonly string $adminUserId,
        public readonly string $targetUserId,
        public readonly DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
    ) {
    }

    public function isActive(): bool
    {
        return $this->endedAt === null;
    }
}
