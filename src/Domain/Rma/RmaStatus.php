<?php

declare(strict_types=1);

namespace App\Domain\Rma;

/**
 * Der Status wird bewusst nicht als eigene Spalte gespeichert, sondern aus
 * sent_at/returned_at abgeleitet — so kann er nie mit den Daten auseinanderlaufen.
 */
enum RmaStatus: string
{
    case Offen = 'offen';                 // noch nicht verschickt
    case Unterwegs = 'unterwegs';         // verschickt, noch keine Rücklieferung
    case Abgeschlossen = 'abgeschlossen'; // zurückgeliefert

    public static function compute(?\DateTimeImmutable $sentAt, ?\DateTimeImmutable $returnedAt): self
    {
        if ($returnedAt !== null) {
            return self::Abgeschlossen;
        }

        if ($sentAt !== null) {
            return self::Unterwegs;
        }

        return self::Offen;
    }

    public function label(): string
    {
        return match ($this) {
            self::Offen => 'Offen',
            self::Unterwegs => 'Unterwegs',
            self::Abgeschlossen => 'Abgeschlossen',
        };
    }

    /** Tailwind-Klassen für ein farbiges Status-Badge. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Offen => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600',
            self::Unterwegs => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700',
            self::Abgeschlossen => 'bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700',
        };
    }
}
