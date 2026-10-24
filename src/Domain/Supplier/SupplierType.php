<?php

declare(strict_types=1);

namespace App\Domain\Supplier;

/** Unterscheidet, ob von diesem Lieferanten gekauft oder nur zugemietet wird. */
enum SupplierType: string
{
    case Kauf = 'kauf';
    case Zumietung = 'zumietung';

    public function label(): string
    {
        return match ($this) {
            self::Kauf => 'Kauf',
            self::Zumietung => 'Zumietung',
        };
    }
}
