<?php

declare(strict_types=1);

namespace App\Domain\Stock;

interface SaleStockLotRepositoryInterface
{
    public function save(SaleStockLot $lot): void;

    public function findById(string $id): ?SaleStockLot;

    /**
     * Chargen mit noch verbleibender Menge (quantityRemaining > 0), älteste
     * zuerst (FIFO) — Grundlage für StockFifoAllocator::allocate().
     *
     * @return SaleStockLot[]
     */
    public function findAvailableByArticleFifo(string $articleId): array;

    /** Aktueller Lagerbestand des reinen Verkaufsbestands (Summe quantityRemaining über alle Chargen). */
    public function sumRemainingByArticle(string $articleId): int;

    /** @return SaleStockLot[] alle Chargen eines Artikels (auch bereits aufgebrauchte), neueste zuerst — für die Bestandsübersicht. */
    public function findByArticle(string $articleId): array;
}
