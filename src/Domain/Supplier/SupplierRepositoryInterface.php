<?php

declare(strict_types=1);

namespace App\Domain\Supplier;

interface SupplierRepositoryInterface
{
    public function save(Supplier $supplier): void;

    public function findById(string $id): ?Supplier;

    /**
     * @param int|null $limit null = ohne LIMIT (z. B. für die Lieferanten-Auswahl beim Anlegen einer Inventurnummer).
     * @return Supplier[] sortiert nach Name
     */
    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie findAll(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countAll(?string $search = null): int;

    /** Wird für die Löschsperre benötigt (Lieferant ist noch einer Inventurnummer zugeordnet). */
    public function isUsedByInventoryNumbers(string $id): bool;

    /** Wird für die Löschsperre benötigt (Lieferant ist noch mindestens einem Beleg zugeordnet). */
    public function isUsedByReceipts(string $id): bool;

    public function delete(string $id): void;
}
