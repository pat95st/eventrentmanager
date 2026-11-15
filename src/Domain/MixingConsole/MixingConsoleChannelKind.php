<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/**
 * Generic kind of a DSP channel of a digital mixing console (migration 154).
 * The manufacturer's own designation (e.g. "Mix Bus", "Aux Send") is stored
 * separately per channel row.
 */
enum MixingConsoleChannelKind: string
{
    case Input = 'input';
    case Aux = 'aux';
    case Group = 'group';
    case Matrix = 'matrix';
    case FxReturn = 'fx_return';
    case Main = 'main';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Input => 'Eingangskanal',
            self::Aux => 'Aux-/Mix-Bus',
            self::Group => 'Subgruppe',
            self::Matrix => 'Matrix',
            self::FxReturn => 'FX-Return',
            self::Main => 'Summe (Main)',
            self::Other => 'Sonstiger Kanal',
        };
    }
}
