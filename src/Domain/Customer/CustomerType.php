<?php

declare(strict_types=1);

namespace App\Domain\Customer;

enum CustomerType: string
{
    case Privat = 'privat';
    case Geschaeftlich = 'geschaeftlich';

    public function label(): string
    {
        return match ($this) {
            self::Privat => 'Privat',
            self::Geschaeftlich => 'Geschäftlich',
        };
    }
}
