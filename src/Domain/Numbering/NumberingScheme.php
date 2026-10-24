<?php

declare(strict_types=1);

namespace App\Domain\Numbering;

/**
 * Zwei Formatierungs-/Zählerschemata je Dokumenttyp (siehe
 * DocumentNumberFormatter, DocumentNumberSequence):
 * - Simple: Präfix + fortlaufende, NIE zurückgesetzte Ziffernfolge, z. B.
 *   "RE000001". Entspricht dem bisherigen, hart kodierten Verhalten von
 *   Rechnungen/Mahnungen.
 * - Yearly: optionales Präfix + Trennzeichen + Jahreszahl + Trennzeichen +
 *   Ziffernfolge, die zu jedem Jahreswechsel automatisch wieder bei 1
 *   beginnt, z. B. "RE-2026-001".
 */
enum NumberingScheme: string
{
    case Simple = 'simple';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Fortlaufend (Präfix + Ziffernfolge)',
            self::Yearly => 'Jährlich (Präfix + Jahr + Ziffernfolge, setzt sich jedes Jahr zurück)',
        };
    }
}
