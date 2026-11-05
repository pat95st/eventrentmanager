<?php

declare(strict_types=1);

namespace App\Domain\Technician;

/**
 * Globale Einstellungen der Techniker-Anfragen (Migration 134): nach wie
 * vielen Stunden ohne Antwort ein angefragter Techniker erneut erinnert
 * wird (mehrere Erinnerungen möglich, aufsteigend).
 */
final class TechnicianSettings
{
    /** @param int[] $reminderHours */
    public function __construct(public readonly array $reminderHours)
    {
    }

    /** Stunden nach der Anfrage, zu der die Erinnerung Nr. $index (0-basiert) fällig wird, oder null. */
    public function reminderDueAfterHours(int $index): ?int
    {
        return $this->reminderHours[$index] ?? null;
    }

    /** "24, 72" für Formulare. */
    public function reminderHoursText(): string
    {
        return implode(', ', $this->reminderHours);
    }

    /** @return int[] aufsteigend, eindeutig, 1–2160 h (90 Tage) */
    public static function parseHours(string $value): ?array
    {
        $hours = [];
        foreach (preg_split('/[\s,;]+/', trim($value)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            if (!ctype_digit($part) || (int) $part < 1 || (int) $part > 2160) {
                return null;
            }
            $hours[] = (int) $part;
        }
        $hours = array_values(array_unique($hours));
        sort($hours);

        return count($hours) > 10 ? null : $hours;
    }
}
