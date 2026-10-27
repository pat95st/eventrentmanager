<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandInterface;

final class UpdateVoucher implements CommandInterface
{
    /** @param string[] $customerIds Leer = für alle Kunden gültig. */
    public function __construct(
        public readonly string $id,
        public readonly string $discountType,
        public readonly string $discountValue,
        public readonly ?string $validFrom,
        public readonly ?string $validUntil,
        public readonly ?string $notes,
        public readonly array $customerIds = [],
    ) {
    }
}
