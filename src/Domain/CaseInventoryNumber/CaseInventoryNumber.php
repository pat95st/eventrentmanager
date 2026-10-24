<?php

declare(strict_types=1);

namespace App\Domain\CaseInventoryNumber;

use DateTimeImmutable;

/** Ein physisches Exemplar eines Case-Modells (App\Domain\CaseItem\CaseItem). */
final class CaseInventoryNumber
{
    public function __construct(
        public readonly string $id,
        public readonly string $caseId,
        public string $number,
        public ?DateTimeImmutable $acquisitionDate,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
