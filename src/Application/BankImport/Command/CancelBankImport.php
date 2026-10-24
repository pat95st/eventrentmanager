<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

use App\Application\Command\CommandInterface;

final class CancelBankImport implements CommandInterface
{
    public function __construct(
        public readonly string $importId,
    ) {
    }
}
