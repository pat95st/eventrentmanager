<?php

declare(strict_types=1);

namespace App\Domain\Sale;

interface SaleRepositoryInterface
{
    public function save(Sale $sale): void;

    public function findById(string $id): ?Sale;

    /** Pro Inventarnummer existiert höchstens ein Verkauf (UNIQUE-Constraint). */
    public function findByInventoryNumber(string $inventoryNumberId): ?Sale;

    /** @return Sale[] */
    public function findByArticle(string $articleId): array;

    /** @return string[] IDs aller bereits verkauften Inventarnummern eines Artikels. */
    public function findSoldInventoryNumberIds(string $articleId): array;

    /**
     * Alle Verkäufe (beide Arten: Vermietpool UND Bestand) für die
     * Verkaufsübersicht — neueste zuerst.
     *
     * @return Sale[]
     */
    public function search(?string $searchTerm, int $limit, int $offset): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie search(), ohne LIMIT/OFFSET. */
    public function countSearch(?string $searchTerm): int;
}
