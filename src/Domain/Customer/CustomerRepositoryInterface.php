<?php

declare(strict_types=1);

namespace App\Domain\Customer;

interface CustomerRepositoryInterface
{
    public function save(Customer $customer): void;

    public function findById(string $id): ?Customer;

    /**
     * @param int|null $limit null = ohne LIMIT (z. B. für Dropdown-Auswahllisten, die alle Kunden benötigen).
     * @return Customer[] sortiert nach Nachname/Firmenname
     */
    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie findAll(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countAll(?string $search = null): int;

    public function delete(string $id): void;
}
