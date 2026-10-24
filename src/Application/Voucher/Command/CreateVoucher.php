<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandInterface;

final class CreateVoucher implements CommandInterface
{
    public function __construct(
        // Optionales Präfix für den automatisch erzeugten Code (z. B. "SOMMER" -> "SOMMER-A1B2C3D4E5").
        public readonly ?string $codePrefix,
        public readonly string $discountType,
        public readonly string $discountValue,
        // Beide optional (Format "Y-m-d"): leer gelassen, greifen sinnvolle Vorgaben (siehe CreateVoucherHandler).
        public readonly ?string $validFrom,
        public readonly ?string $validUntil,
        public readonly ?string $notes,
    ) {
    }
}
