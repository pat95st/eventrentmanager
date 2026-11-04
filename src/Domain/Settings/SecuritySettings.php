<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Sicherheits-Einstellungen (Migration 127, Singleton-Zeile): automatische
 * Abmeldung nach Inaktivität. Änderbar nur durch Administratoren.
 */
final class SecuritySettings
{
    public const DEFAULT_SESSION_TIMEOUT_MINUTES = 120;
    public const MIN_SESSION_TIMEOUT_MINUTES = 5;
    /** 30 Tage */
    public const MAX_SESSION_TIMEOUT_MINUTES = 43200;

    public function __construct(
        public int $sessionTimeoutMinutes = self::DEFAULT_SESSION_TIMEOUT_MINUTES,
    ) {
    }

    public function sessionTimeoutSeconds(): int
    {
        return $this->sessionTimeoutMinutes * 60;
    }

    /** Lesbare Dauer, z. B. "2 Stunden", "90 Minuten", "1 Tag". */
    public static function formatMinutes(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            $days = intdiv($minutes, 1440);

            return $days . ($days === 1 ? ' Tag' : ' Tage');
        }
        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours . ($hours === 1 ? ' Stunde' : ' Stunden');
        }

        return $minutes . ($minutes === 1 ? ' Minute' : ' Minuten');
    }
}
