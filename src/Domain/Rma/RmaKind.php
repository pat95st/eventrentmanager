<?php

declare(strict_types=1);

namespace App\Domain\Rma;

enum RmaKind: string
{
    case Extern = 'extern';
    case Eigenreparatur = 'eigenreparatur';

    public function label(): string
    {
        return match ($this) {
            self::Extern => 'Extern (Werkstatt/Hersteller)',
            self::Eigenreparatur => 'Eigenreparatur',
        };
    }
}
