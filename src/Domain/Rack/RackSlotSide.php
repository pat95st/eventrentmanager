<?php

declare(strict_types=1);

namespace App\Domain\Rack;

/**
 * Belegte Hälfte eines 9,5-Zoll-Geräts in einem 19-Zoll-Rack (Migration 128).
 * Ohne Seite (null) belegt ein Einbau die ganze Rackbreite.
 */
enum RackSlotSide: string
{
    case Left = 'left';
    case Right = 'right';

    public function label(): string
    {
        return match ($this) {
            self::Left => 'links',
            self::Right => 'rechts',
        };
    }

    public function other(): self
    {
        return $this === self::Left ? self::Right : self::Left;
    }

    /** Überschneiden sich zwei Belegungen in der Breite? (null = ganze Breite) */
    public static function overlaps(?self $a, ?self $b): bool
    {
        return $a === null || $b === null || $a === $b;
    }
}
