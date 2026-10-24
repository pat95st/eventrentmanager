<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Application\Command\CommandInterface;

final class CreateReceipt implements CommandInterface
{
    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $uploadedFile Aus $_FILES */
    public function __construct(
        public readonly string $supplierId,
        public readonly string $receiptNumber,
        public readonly string $rawAmount,
        public readonly string $rawReceiptDate,
        public readonly string $category,
        public readonly string $note,
        public readonly ?array $uploadedFile,
    ) {
    }
}
