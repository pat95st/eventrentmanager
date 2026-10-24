<?php

declare(strict_types=1);

namespace App\Domain\CaseInventoryNumber;

interface CaseInventoryNumberRepositoryInterface
{
    public function save(CaseInventoryNumber $caseInventoryNumber): void;

    public function findById(string $id): ?CaseInventoryNumber;

    /** @return CaseInventoryNumber[] */
    public function findByCase(string $caseId): array;

    public function existsByNumber(string $number, ?string $excludeId = null): bool;

    public function delete(string $id): void;
}
