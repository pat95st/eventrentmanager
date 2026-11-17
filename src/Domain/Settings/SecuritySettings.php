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
        // Whether holding Shift may skip confirmations at all (migration 174).
        // Users can additionally switch it off for themselves (User::$confirmBypassEnabled).
        public bool $confirmBypassEnabled = true,
        /**
         * Deviations from ConfirmCatalog's defaults: key => true (confirmation
         * is required, Shift does not skip it) or false (skippable). Only
         * deviations are stored, so new catalog entries get their default.
         *
         * @var array<string, bool>
         */
        public array $confirmOverrides = [],
    ) {
    }

    public function isConfirmRequired(string $key): bool
    {
        return $this->confirmOverrides[$key] ?? ConfirmCatalog::requiredByDefault($key);
    }

    /** @return list<string> keys of all confirmations Shift must not skip */
    public function requiredConfirmKeys(): array
    {
        return array_values(array_filter(ConfirmCatalog::keys(), fn (string $key): bool => $this->isConfirmRequired($key)));
    }

    /**
     * Replaces the overrides so that exactly $requiredKeys are required.
     *
     * @param list<string> $requiredKeys unknown keys are ignored
     */
    public function setRequiredConfirmKeys(array $requiredKeys): void
    {
        $this->confirmOverrides = [];
        foreach (ConfirmCatalog::keys() as $key) {
            $required = in_array($key, $requiredKeys, true);
            if ($required !== ConfirmCatalog::requiredByDefault($key)) {
                $this->confirmOverrides[$key] = $required;
            }
        }
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
