<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class CreateBankAccount implements CommandInterface
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $accountHolder,
        public readonly ?string $bankName,
        public readonly string $iban,
        public readonly ?string $bic,
        public readonly bool $isDefault,
    ) {
    }
}
