<?php

declare(strict_types=1);

namespace App\Domain\Rack;

enum RackWidth: string
{
    case Full = '19';
    case Half = '9.5';

    public function label(): string
    {
        return match ($this) {
            self::Full => '19 Zoll',
            self::Half => '9,5 Zoll',
        };
    }
}
