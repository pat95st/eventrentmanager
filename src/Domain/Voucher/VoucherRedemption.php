<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use DateTimeImmutable;

/** Ordnet einen Gutschein (per voucherId) genau einer Vermietung (per rentalId) zu, siehe voucher_redemptions. */
final class VoucherRedemption
{
    public function __construct(
        public readonly string $id,
        public readonly string $voucherId,
        public readonly string $rentalId,
        public readonly DateTimeImmutable $redeemedAt,
    ) {
    }
}
