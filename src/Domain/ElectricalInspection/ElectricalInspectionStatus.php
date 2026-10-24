<?php

declare(strict_types=1);

namespace App\Domain\ElectricalInspection;

use DateTimeImmutable;

/**
 * Der Status wird bewusst nicht gespeichert, sondern aus passed/validUntil
 * abgeleitet — so kann er nie mit den Daten auseinanderlaufen. "Bald fällig"
 * greift innerhalb der letzten 4 Wochen vor Ablauf der Gültigkeit.
 */
enum ElectricalInspectionStatus: string
{
    case Bestanden = 'bestanden';       // gültig, nicht bestanden nicht relevant
    case BaldFaellig = 'bald_faellig';  // gültig, aber läuft in Kürze ab
    case Abgelaufen = 'abgelaufen';     // Gültigkeit überschritten
    case NichtBestanden = 'nicht_bestanden'; // Prüfung nicht bestanden

    public static function compute(bool $passed, DateTimeImmutable $validUntil, DateTimeImmutable $today): self
    {
        if (!$passed) {
            return self::NichtBestanden;
        }

        if ($validUntil < $today) {
            return self::Abgelaufen;
        }

        $warningThreshold = $today->modify('+28 days');
        if ($validUntil <= $warningThreshold) {
            return self::BaldFaellig;
        }

        return self::Bestanden;
    }

    public function label(): string
    {
        return match ($this) {
            self::Bestanden => 'Gültig',
            self::BaldFaellig => 'Bald fällig',
            self::Abgelaufen => 'Abgelaufen',
            self::NichtBestanden => 'Nicht bestanden',
        };
    }

    /** Tailwind-Klassen für ein farbiges Status-Badge. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Bestanden => 'bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700',
            self::BaldFaellig => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700',
            self::Abgelaufen, self::NichtBestanden => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-300 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-700',
        };
    }
}
