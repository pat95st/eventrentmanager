<?php

declare(strict_types=1);

namespace App\Domain\Federation;

enum PartnerRequestKind: string
{
    case Material = 'material';
    case Technician = 'technician';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Material',
            self::Technician => 'Techniker',
        };
    }
}
