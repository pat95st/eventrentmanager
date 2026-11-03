<?php

declare(strict_types=1);

namespace App\Domain\PowerDistributor;

enum ProtectiveDeviceType: string
{
    case Rcd = 'rcd';
    case Mcb = 'mcb';
    case Rcbo = 'rcbo';

    public function label(): string
    {
        return match ($this) {
            self::Rcd => 'RCD (FI)',
            self::Mcb => 'LS-Schalter',
            self::Rcbo => 'FI/LS-Kombination',
        };
    }

    public function hasResidualProtection(): bool
    {
        return $this !== self::Mcb;
    }

    public function hasOvercurrentProtection(): bool
    {
        return $this !== self::Rcd;
    }
}
