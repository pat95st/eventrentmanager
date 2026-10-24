<?php

declare(strict_types=1);

namespace App\Domain\Rma;

interface RmaRepositoryInterface
{
    public function save(Rma $rma): void;

    public function findById(string $id): ?Rma;

    /** @return Rma[] neueste zuerst (nach sent_at, dann created_at) */
    public function findByInventoryNumber(string $inventoryNumberId): array;

    /** @return Rma[] alle RMA-Vorgänge, neueste zuerst (nach sent_at, dann created_at) */
    public function findAll(): array;

    public function countByInventoryNumber(string $inventoryNumberId): int;

    public function delete(string $id): void;
}
