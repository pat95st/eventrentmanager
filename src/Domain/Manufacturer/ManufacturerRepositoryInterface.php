<?php

declare(strict_types=1);

namespace App\Domain\Manufacturer;

interface ManufacturerRepositoryInterface
{
    public function save(Manufacturer $manufacturer): void;

    public function findById(string $id): ?Manufacturer;

    /** @return Manufacturer[] alphabetisch sortiert */
    public function findAll(): array;

    public function existsByName(string $name, ?string $excludeId = null): bool;

    public function existsByPrefix(string $prefix, ?string $excludeId = null): bool;

    public function isUsedByArticles(string $id): bool;

    public function isUsedByCaseItems(string $id): bool;

    public function delete(string $id): void;
}
