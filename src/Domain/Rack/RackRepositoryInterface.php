<?php

declare(strict_types=1);

namespace App\Domain\Rack;

interface RackRepositoryInterface
{
    public function save(Rack $rack): void;

    public function findById(string $id): ?Rack;

    /** @return Rack[] nach Name sortiert */
    public function findAll(?string $search = null): array;

    public function delete(string $id): void;

    public function existsByInventoryNumber(string $number, ?string $excludeId = null): bool;

    /** Rack, das diesem Geräte-Exemplar fest zugeordnet ist. */
    public function findByFixedInventoryNumber(string $inventoryNumberId): ?Rack;

    /**
     * @param string[] $inventoryNumberIds
     * @return array<string, Rack> Geräte-Exemplar-ID => fest zugeordnetes Rack
     */
    public function findByFixedInventoryNumbers(array $inventoryNumberIds): array;

    /** @return RackSlot[] nach HE sortiert */
    public function findSlots(string $rackId): array;

    public function findSlotById(string $slotId): ?RackSlot;

    public function findSlotByInventoryNumber(string $inventoryNumberId): ?RackSlot;

    public function saveSlot(RackSlot $slot): void;

    public function deleteSlot(string $slotId): void;
}
