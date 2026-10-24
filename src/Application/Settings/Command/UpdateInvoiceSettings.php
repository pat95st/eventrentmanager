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
        public readonly string $paymentTermDays, // als String, wird im Handler validiert/geparst (siehe UpdateInvoiceSettingsHandler)
        public readonly ?string $bankName,
        public readonly ?string $iban,
        public readonly ?string $bic,
    ) {
    }
}
