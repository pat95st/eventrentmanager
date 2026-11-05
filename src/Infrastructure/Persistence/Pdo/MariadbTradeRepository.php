<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Technician\Trade;
use App\Domain\Technician\TradeRepositoryInterface;
use DateTimeImmutable;
use PDO;

/** Gewerke (Migration 134). */
final class MariadbTradeRepository implements TradeRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->pdo->query('SELECT * FROM trades ORDER BY sort_order, name')->fetchAll());
    }

    public function findById(string $id): ?Trade
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trades WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByName(string $name): ?Trade
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trades WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(Trade $trade): void
    {
        $this->pdo->prepare(
            'INSERT INTO trades (id, name, sort_order, created_at, updated_at) VALUES (:id, :name, :sort, :created, :updated)
             ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order), updated_at = VALUES(updated_at)'
        )->execute([
            'id' => $trade->id,
            'name' => $trade->name,
            'sort' => $trade->sortOrder,
            'created' => $trade->createdAt->format('Y-m-d H:i:s'),
            'updated' => $trade->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM trades WHERE id = :id')->execute(['id' => $id]);
    }

    public function tradeIdsForRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT trade_id FROM rental_trades WHERE rental_id = :id');
        $stmt->execute(['id' => $rentalId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function saveTradeIdsForRental(string $rentalId, array $tradeIds): void
    {
        $this->pdo->prepare('DELETE FROM rental_trades WHERE rental_id = :id')->execute(['id' => $rentalId]);
        $insert = $this->pdo->prepare('INSERT INTO rental_trades (rental_id, trade_id) VALUES (:rental, :trade)');
        foreach (array_unique($tradeIds) as $tradeId) {
            $insert->execute(['rental' => $rentalId, 'trade' => $tradeId]);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Trade
    {
        return new Trade($row['id'], $row['name'], (int) $row['sort_order'], new DateTimeImmutable($row['created_at']), new DateTimeImmutable($row['updated_at']));
    }
}
