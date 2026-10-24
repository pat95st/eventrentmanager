<?php

declare(strict_types=1);

namespace App\Domain\CaseItem;

interface CaseItemRepositoryInterface
{
    public function save(CaseItem $caseItem): void;

    public function findById(string $id): ?CaseItem;

    /** @return CaseItem[] Höchstens $limit Zeilen, ab $offset (siehe countSearch() für die Gesamtanzahl). */
    public function search(?string $searchTerm, ?string $manufacturerId, int $limit, int $offset): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie search(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countSearch(?string $searchTerm, ?string $manufacturerId): int;

    public function countByManufacturer(string $manufacturerId): int;

    /** Prüft, ob innerhalb eines Herstellers bereits ein Case dieses Präfix nutzt. */
    public function existsByPrefix(string $manufacturerId, string $prefix, ?string $excludeId = null): bool;

    public function delete(string $id): void;
}
