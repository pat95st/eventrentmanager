<?php

declare(strict_types=1);

namespace App\Domain\Manufacturer;

interface ManufacturerRepositoryInterface
{
    public function save(Manufacturer $manufacturer): void;

    public function findById(string $id): ?Manufacturer;

    /**
     * $search/$limit/$offset bleiben optional (Default: alle, unsortiert
     * nach Übereinstimmung, alphabetisch) — ohne Angabe identisches
     * Verhalten wie zuvor (siehe ListManufacturersHandler, das weiterhin
     * ohne Parameter aufruft). Mit $search gefüllt: Treffer über den Namen
     * (siehe MariadbManufacturerRepository::buildSearchWhere()), genutzt von
     * der neuen Live-Suche (ManufacturerController::search()).
     *
     * @return Manufacturer[] alphabetisch sortiert
     */
    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array;

    public function countAll(?string $search = null): int;

    public function existsByName(string $name, ?string $excludeId = null): bool;

    public function existsByPrefix(string $prefix, ?string $excludeId = null): bool;

    public function isUsedByArticles(string $id): bool;

    public function isUsedByCaseItems(string $id): bool;

    public function delete(string $id): void;
}
