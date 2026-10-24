<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandInterface;

final class RedeemVoucherForRental implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $code,
    ) {
    }
}
