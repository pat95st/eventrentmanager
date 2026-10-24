<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

enum VoucherDiscountType: string
{
    case Percentage = 'prozent';
    case FixedAmount = 'betrag';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Prozent',
            self::FixedAmount => 'Betrag (EUR)',
        };
    }
}
