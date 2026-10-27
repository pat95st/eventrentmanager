<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandInterface;

final class CreateVoucher implements CommandInterface
{
    /** @param string[] $customerIds Leer = für alle Kunden gültig (siehe ValidatesVoucherInput::sanitizeVoucherCustomerIds()). */
    public function __construct(
        // Optionales Präfix für den automatisch erzeugten Code (z. B. "SOMMER" -> "SOMMER-A1B2C3D4E5").
        public readonly ?string $codePrefix,
        public readonly string $discountType,
        public readonly string $discountValue,
        // Beide optional (Format "Y-m-d"): leer gelassen, greifen sinnvolle Vorgaben (siehe CreateVoucherHandler).
        public readonly ?string $validFrom,
        public readonly ?string $validUntil,
        public readonly ?string $notes,
        public readonly array $customerIds = [],
        // Anzahl GLEICHARTIGER Gutscheine, die in einem Zug angelegt werden
        // sollen (jeder mit eigenem, eindeutigem Code) — siehe
        // Aufgabenstellung "mehrere Gutscheine auf einmal erstellen".
        public readonly int $quantity = 1,
    ) {
    }
}
