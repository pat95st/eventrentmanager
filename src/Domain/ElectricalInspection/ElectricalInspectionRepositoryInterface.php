<?php

declare(strict_types=1);

namespace App\Domain\ElectricalInspection;

interface ElectricalInspectionRepositoryInterface
{
    public function save(ElectricalInspection $inspection): void;

    public function findById(string $id): ?ElectricalInspection;

    /** @return ElectricalInspection[] neueste zuerst (nach inspected_at, dann created_at) */
    public function findByInventoryNumber(string $inventoryNumberId): array;

    /** Jüngste erfasste Prüfung einer Inventurnummer, oder null wenn noch keine erfasst wurde. */
    public function findLatestByInventoryNumber(string $inventoryNumberId): ?ElectricalInspection;

    public function countByInventoryNumber(string $inventoryNumberId): int;

    public function delete(string $id): void;
}
