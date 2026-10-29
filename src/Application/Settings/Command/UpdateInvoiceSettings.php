<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateInvoiceSettings implements CommandInterface
{
    public function __construct(
        public readonly bool $isSmallBusiness,
        public readonly string $vatRatePercent,
        public readonly ?string $taxNumber,
        public readonly ?string $vatId,
        // Zahlungsziel und Bankverbindung werden seit Migration 105/106 über
        // Zahlungsarten bzw. Bankverbindungen gepflegt — null = bisherigen
        // (nur noch als Fallback genutzten) Wert unverändert lassen.
        public readonly ?string $paymentTermDays = null, // als String, wird im Handler validiert/geparst
        public readonly ?string $bankName = null,
        public readonly ?string $iban = null,
        public readonly ?string $bic = null,
    ) {
    }
}
