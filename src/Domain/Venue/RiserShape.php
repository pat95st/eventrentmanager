<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/**
 * Form eines Risers. Welche Maße eine Form braucht, steht hier an EINER
 * Stelle: Rechteck und Dreieck haben Breite und Tiefe, Kreis und Halbkreis
 * nur einen Durchmesser (gespeichert als Breite).
 */
enum RiserShape: string
{
    case Rectangle = 'rectangle';
    case Circle = 'circle';
    case Semicircle = 'semicircle';
    case Triangle = 'triangle';

    /** Braucht die Form neben der Breite eine eigene Tiefe? */
    public function hasDepth(): bool
    {
        return $this === self::Rectangle || $this === self::Triangle;
    }

    /** Ist die "Breite" dieser Form ein Durchmesser? */
    public function widthIsDiameter(): bool
    {
        return $this === self::Circle || $this === self::Semicircle;
    }

    /** Übersetzungsschlüssel der Bezeichnung (siehe resources/lang). */
    public function labelKey(): string
    {
        return 'venues.risers.shape.' . $this->value;
    }
}
