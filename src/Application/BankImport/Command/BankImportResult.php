<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

use App\Domain\BankImport\BankStatementFormat;

final class BankImportResult
{
    /** @param BankImportRow[] $rows */
    public function __construct(
        public readonly string $importId,
        public readonly BankStatementFormat $format,
        public readonly array $rows,
    ) {
    }
}
