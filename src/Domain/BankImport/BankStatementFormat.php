<?php

declare(strict_types=1);

namespace App\Domain\BankImport;

/**
 * Die beiden unterstützten Kontoauszugsformate.
 */
enum BankStatementFormat: string
{
    case Mt940 = 'mt940';
    case Camt053 = 'camt053';

    public function label(): string
    {
        return match ($this) {
            self::Mt940 => 'MT940',
            self::Camt053 => 'CAMT.053',
        };
    }
}
