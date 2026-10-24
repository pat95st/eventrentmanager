<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Stock\SaleStockConsumption;
use App\Domain\Stock\SaleStockConsumptionRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbSaleStockConsumptionRepository implements SaleStockConsumptionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(SaleStockConsumption $consumption): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sale_stock_consumptions (id, sale_id, stock_lot_id, quantity, unit_purchase_price, created_at)
             VALUES (:id, :sale_id, :stock_lot_id, :quantity, :unit_purchase_price, :created_at)'
        );
        $stmt->execute([
            'id' => $consumption->id,
            'sale_id' => $consumption->saleId,
            'stock_lot_id' => $consumption->stockLotId,
            'quantity' => $consumption->quantity,
            'unit_purchase_price' => $consumption->unitPurchasePrice,
            'created_at' => $consumption->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findBySale(string $saleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sale_stock_consumptions WHERE sale_id = :sale_id ORDER BY created_at ASC');
        $stmt->execute(['sale_id' => $saleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    private function hydrate(array $row): SaleStockConsumption
    {
        return new SaleStockConsumption(
            id: $row['id'],
            saleId: $row['sale_id'],
            stockLotId: $row['stock_lot_id'],
            quantity: (int) $row['quantity'],
            unitPurchasePrice: $row['unit_purchase_price'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
