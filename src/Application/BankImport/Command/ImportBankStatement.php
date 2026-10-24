<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

use App\Application\Command\CommandInterface;

final class ImportBankStatement implements CommandInterface
{
    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $uploadedFile */
    public function __construct(
        public readonly array $uploadedFile,
    ) {
    }
}
