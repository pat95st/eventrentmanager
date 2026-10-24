<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Stock\SaleStockLot;
use App\Domain\Stock\SaleStockLotRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbSaleStockLotRepository implements SaleStockLotRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(SaleStockLot $lot): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sale_stock_lots (id, article_id, purchase_order_item_id, unit_purchase_price, quantity_received, quantity_remaining, received_at, created_at)
             VALUES (:id, :article_id, :purchase_order_item_id, :unit_purchase_price, :quantity_received, :quantity_remaining, :received_at, :created_at)
             ON DUPLICATE KEY UPDATE quantity_remaining = :quantity_remaining2'
        );
        $stmt->execute([
            'id' => $lot->id,
            'article_id' => $lot->articleId,
            'purchase_order_item_id' => $lot->purchaseOrderItemId,
            'unit_purchase_price' => $lot->unitPurchasePrice,
            'quantity_received' => $lot->quantityReceived,
            'quantity_remaining' => $lot->quantityRemaining,
            'received_at' => $lot->receivedAt->format('Y-m-d H:i:s'),
            'created_at' => $lot->createdAt->format('Y-m-d H:i:s'),
            'quantity_remaining2' => $lot->quantityRemaining,
        ]);
    }

    public function findById(string $id): ?SaleStockLot
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sale_stock_lots WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAvailableByArticleFifo(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM sale_stock_lots
             WHERE article_id = :article_id AND quantity_remaining > 0
             ORDER BY received_at ASC, created_at ASC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function sumRemainingByArticle(string $articleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(quantity_remaining), 0) FROM sale_stock_lots WHERE article_id = :article_id');
        $stmt->execute(['article_id' => $articleId]);

        return (int) $stmt->fetchColumn();
    }

    public function findByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sale_stock_lots WHERE article_id = :article_id ORDER BY received_at DESC, created_at DESC');
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    private function hydrate(array $row): SaleStockLot
    {
        return new SaleStockLot(
            id: $row['id'],
            articleId: $row['article_id'],
            purchaseOrderItemId: $row['purchase_order_item_id'],
            unitPurchasePrice: $row['unit_purchase_price'],
            quantityReceived: (int) $row['quantity_received'],
            quantityRemaining: (int) $row['quantity_remaining'],
            receivedAt: new DateTimeImmutable($row['received_at']),
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
