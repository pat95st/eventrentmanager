<?php

declare(strict_types=1);

namespace App\Domain\Technician;

interface TradeRepositoryInterface
{
    /** @return Trade[] nach Sortierung und Name */
    public function findAll(): array;

    public function findById(string $id): ?Trade;

    public function findByName(string $name): ?Trade;

    public function save(Trade $trade): void;

    public function delete(string $id): void;

    /** @return string[] Gewerke-IDs, die an der Veranstaltung benötigt werden */
    public function tradeIdsForRental(string $rentalId): array;

    /** @param string[] $tradeIds */
    public function saveTradeIdsForRental(string $rentalId, array $tradeIds): void;
}
