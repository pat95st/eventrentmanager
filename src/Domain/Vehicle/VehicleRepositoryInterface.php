<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

interface VehicleRepositoryInterface
{
    public function save(Vehicle $vehicle): void;

    public function findById(string $id): ?Vehicle;

    /** @return Vehicle[] */
    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array;

    /** @return Vehicle[] */
    public function findByCategory(VehicleCategory $category): array;

    /**
     * @param string[] $ids
     * @return Vehicle[] sorted by name, unknown ids are skipped
     */
    public function findByIds(array $ids): array;

    public function countAll(?string $search = null): int;

    /** Ist dieses Fahrzeug einer Beladungsliste zugeordnet (rental_vehicle_loads)? */
    public function isUsedByVehicleLoads(string $id): bool;

    /** Ist dieses Fahrzeug einer Zugfahrzeug-Zuordnung zugeordnet (rental_vehicle_tows, als Zugfahrzeug oder Anhänger)? */
    public function isUsedByVehicleTows(string $id): bool;

    /** Vehicle of a logbook QR code (migration 185). */
    public function findByLogbookQrToken(string $token): ?Vehicle;

    public function delete(string $id): void;
}
