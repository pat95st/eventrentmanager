<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/**
 * Standardgrößen von Bühnenelementen (Podesten), aus denen mobile Bühnen
 * gebaut werden. Alle Größen liegen im 0,5-m-Raster; der Aufbauplaner
 * (StageElementPlanner) rechnet deshalb in "Zellen" von 0,5 × 0,5 m.
 *
 * Eine weitere Größe braucht nur einen neuen Fall samt Zellmaßen hier — der
 * Planer, das Formular und die Anzeige übernehmen sie automatisch.
 */
enum StageElementSize: string
{
    case TwoByOne = '2x1';
    case OneByOne = '1x1';
    case TwoByHalf = '2x0.5';
    case OneByHalf = '1x0.5';

    /** Kantenlänge einer Rasterzelle in Metern. */
    public const CELL_M = 0.5;

    /** Lange Seite in Rasterzellen (0,5 m). */
    public function longCells(): int
    {
        return match ($this) {
            self::TwoByOne, self::TwoByHalf => 4,
            self::OneByOne, self::OneByHalf => 2,
        };
    }

    /** Kurze Seite in Rasterzellen (0,5 m). */
    public function shortCells(): int
    {
        return match ($this) {
            self::TwoByOne, self::OneByOne => 2,
            self::TwoByHalf, self::OneByHalf => 1,
        };
    }

    public function areaCells(): int
    {
        return $this->longCells() * $this->shortCells();
    }

    /** z. B. "2 × 1 m" bzw. "2 × 0,5 m". */
    public function label(): string
    {
        $format = static fn (int $cells): string => str_replace('.', ',', (string) ($cells * self::CELL_M));

        return $format($this->longCells()) . ' × ' . $format($this->shortCells()) . ' m';
    }

    /** @return StageElementSize[] größte zuerst — zugleich die Anzeigereihenfolge. */
    public static function all(): array
    {
        return [self::TwoByOne, self::OneByOne, self::TwoByHalf, self::OneByHalf];
    }
}
