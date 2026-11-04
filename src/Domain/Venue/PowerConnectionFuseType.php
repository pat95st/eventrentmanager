<?php

declare(strict_types=1);

namespace App\Domain\Venue;

/**
 * Art der Absicherung eines Stromanschlusses (Migration 129): LS-Schalter
 * (bisheriger Standard) oder Schmelzsicherung Neozed/Diazed. Bei
 * dreiphasigen Anschlüssen (CEE rot) sind das drei einzelne Sicherungen.
 */
enum PowerConnectionFuseType: string
{
    case CircuitBreaker = 'mcb';
    case Neozed = 'neozed';
    case Diazed = 'diazed';

    public function label(): string
    {
        return match ($this) {
            self::CircuitBreaker => 'LS-Schalter',
            self::Neozed => 'Neozed-Sicherung',
            self::Diazed => 'Diazed-Sicherung',
        };
    }

    /** Schmelzsicherung, für die Ersatz vor Ort bereitliegen muss? */
    public function isFuse(): bool
    {
        return $this !== self::CircuitBreaker;
    }
}
