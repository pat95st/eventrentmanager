<?php

declare(strict_types=1);

namespace App\Domain\Display;

/**
 * Role of a display device (migration 168). Decides the layout the client
 * shows; a device can be switched between roles at any time, so the same
 * hardware/client software serves the stage and the FOH.
 */
enum DisplayRole: string
{
    case Stage = 'stage';
    case Foh = 'foh';

    public function label(): string
    {
        return match ($this) {
            self::Stage => 'Stagedisplay',
            self::Foh => 'FOH-Display',
        };
    }

    public static function fromInput(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Stage;
    }
}
