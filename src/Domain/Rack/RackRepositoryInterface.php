<?php

declare(strict_types=1);

namespace App\Domain\Rack;

/** Rack-Typen, ihre Exemplare und deren Bestückung (Migrationen 115/124). */
interface RackRepositoryInterface
{
    public function save(Rack $rack): void;

    public function findById(string $id): ?Rack;

    /** @return Rack[] */
    public function findAll(?string $search = null): array;

    /** Löscht den Rack-Typ samt Exemplaren und Bestückung. */
    public function delete(string $id): void;

    public function existsByPrefix(string $manufacturerId, string $prefix, ?string $excludeId = null): bool;

    public function saveExemplar(RackExemplar $exemplar): void;

    public function findExemplarById(string $id): ?RackExemplar;

    /** @return RackExemplar[] nach Rack-Nummer sortiert */
    public function findExemplars(string $rackId): array;

    public function deleteExemplar(string $id): void;

    public function existsExemplarByInventoryNumber(string $number, ?string $excludeId = null): bool;

    public function existsExemplarByRackNumber(string $number, ?string $excludeId = null): bool;

    public function findExemplarByFixedInventoryNumber(string $inventoryNumberId): ?RackExemplar;

    /**
     * @param string[] $inventoryNumberIds
     * @return array<string, FixedRack> Geräte-Exemplar-ID => fest zugeordnetes Rack-Exemplar
     */
    public function findFixedRacks(array $inventoryNumberIds): array;

    /** @return RackSlot[] */
    public function findSlots(string $exemplarId): array;

    public function findSlotById(string $slotId): ?RackSlot;

    public function findSlotByInventoryNumber(string $inventoryNumberId): ?RackSlot;

    public function saveSlot(RackSlot $slot): void;

    public function deleteSlot(string $slotId): void;
}
