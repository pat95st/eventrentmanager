<?php

declare(strict_types=1);

namespace App\Domain\CaseInventoryNumber;

interface CaseInventoryNumberImageRepositoryInterface
{
    /** @return CaseInventoryNumberImage[] nach Position sortiert */
    public function findByInventoryNumber(string $caseInventoryNumberId): array;

    public function findById(string $id): ?CaseInventoryNumberImage;

    public function save(CaseInventoryNumberImage $image): void;

    public function delete(string $id): void;
}
