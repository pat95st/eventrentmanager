<?php

declare(strict_types=1);

namespace App\Domain\PowerDistributor;

enum ProtectiveDeviceType: string
{
    case Rcd = 'rcd';
    case Mcb = 'mcb';
    case Rcbo = 'rcbo';
    // Schmelzsicherung Neozed/Diazed (Nutzerwunsch): nur 1-polig oder 3 einzelne für CEE rot.
    case Fuse = 'fuse';

    public function label(): string
    {
        return match ($this) {
            self::Rcd => 'RCD (FI)',
            self::Mcb => 'LS-Schalter',
            self::Rcbo => 'FI/LS-Kombination',
            self::Fuse => 'Schmelzsicherung (Neozed/Diazed)',
        };
    }

    public function hasResidualProtection(): bool
    {
        return $this === self::Rcd || $this === self::Rcbo;
    }

    /** Kurzzeichen für Listen und Schemata. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Rcd => 'FI',
            self::Mcb => 'LS',
            self::Rcbo => 'FI/LS',
            self::Fuse => 'Sicherung',
        };
    }

    /** Kann hinter einem RCD (FI) liegen? */
    public function canBeBehindRcd(): bool
    {
        return $this === self::Mcb || $this === self::Fuse;
    }

    public function hasOvercurrentProtection(): bool
    {
        return $this !== self::Rcd;
    }
}
