<?php

declare(strict_types=1);

namespace App\Domain\Warehouse;

interface WarehouseRepositoryInterface
{
    public function save(Warehouse $warehouse): void;

    public function findById(string $id): ?Warehouse;

    /**
     * @param int|null $limit null = ohne LIMIT (z. B. für die Lagerort-Auswahl im Veranstaltungsformular).
     * @return Warehouse[] alphabetisch nach Name
     */
    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie findAll(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countAll(?string $search = null): int;

    public function delete(string $id): void;
}
