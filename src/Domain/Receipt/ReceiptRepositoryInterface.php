<?php

declare(strict_types=1);

namespace App\Domain\Receipt;

use DateTimeImmutable;

interface ReceiptRepositoryInterface
{
    public function save(Receipt $receipt): void;

    public function findById(string $id): ?Receipt;

    /**
     * @param string[] $ids
     * @return Receipt[]
     */
    public function findByIds(array $ids): array;

    /** @return Receipt[] */
    public function search(
        ?string $searchTerm,
        ?string $supplierId,
        ?ReceiptStatus $status,
        ?ReceiptCategory $category,
        int $limit,
        int $offset,
    ): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie search(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countSearch(
        ?string $searchTerm,
        ?string $supplierId,
        ?ReceiptStatus $status,
        ?ReceiptCategory $category,
    ): int;

    public function delete(string $id): void;

    /**
     * Als bezahlt markierte Belege mit Zahlungsdatum im angegebenen Zeitraum
     * (inklusive Grenzen) — Grundlage für die Einnahmen-/Ausgabenübersicht
     * (siehe GetIncomeExpenseOverviewHandler), analog zu
     * InvoiceRepositoryInterface::findPaidBetween().
     *
     * @return Receipt[]
     */
    public function findPaidBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
