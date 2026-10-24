<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Sale\Sale;
use App\Domain\Sale\SaleRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbSaleRepository implements SaleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Sale $sale): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sales (id, inventory_number_id, article_id, quantity, customer_id, invoice_id, sale_price, cost_of_goods_sold, shipping_amount, sold_at, note, created_at)
             VALUES (:id, :inventory_number_id, :article_id, :quantity, :customer_id, :invoice_id, :sale_price, :cost_of_goods_sold, :shipping_amount, :sold_at, :note, :created_at)
             ON DUPLICATE KEY UPDATE customer_id = :customer_id2, invoice_id = :invoice_id2, sale_price = :sale_price2,
                cost_of_goods_sold = :cost_of_goods_sold2, shipping_amount = :shipping_amount2, sold_at = :sold_at2, note = :note2'
        );

        $stmt->execute([
            'id' => $sale->id,
            'inventory_number_id' => $sale->inventoryNumberId,
            'article_id' => $sale->articleId,
            'quantity' => $sale->quantity,
            'customer_id' => $sale->customerId,
            'invoice_id' => $sale->invoiceId,
            'sale_price' => $sale->salePrice,
            'cost_of_goods_sold' => $sale->costOfGoodsSold,
            'shipping_amount' => $sale->shippingAmount,
            'sold_at' => $sale->soldAt->format('Y-m-d H:i:s'),
            'note' => $sale->note,
            'created_at' => $sale->createdAt->format('Y-m-d H:i:s'),
            'customer_id2' => $sale->customerId,
            'invoice_id2' => $sale->invoiceId,
            'sale_price2' => $sale->salePrice,
            'cost_of_goods_sold2' => $sale->costOfGoodsSold,
            'shipping_amount2' => $sale->shippingAmount,
            'sold_at2' => $sale->soldAt->format('Y-m-d H:i:s'),
            'note2' => $sale->note,
        ]);
    }

    public function findById(string $id): ?Sale
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByInventoryNumber(string $inventoryNumberId): ?Sale
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales WHERE inventory_number_id = :inventory_number_id');
        $stmt->execute(['inventory_number_id' => $inventoryNumberId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales WHERE article_id = :article_id ORDER BY sold_at DESC');
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findSoldInventoryNumberIds(string $articleId): array
    {
        $stmt = $this->pdo->prepare('SELECT inventory_number_id FROM sales WHERE article_id = :article_id AND inventory_number_id IS NOT NULL');
        $stmt->execute(['article_id' => $articleId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function search(?string $searchTerm, int $limit, int $offset): array
    {
        [$where, $params] = $this->searchConditions($searchTerm);

        $stmt = $this->pdo->prepare(
            "SELECT sales.* FROM sales
             LEFT JOIN articles ON articles.id = sales.article_id
             LEFT JOIN customers ON customers.id = sales.customer_id
             {$where}
             ORDER BY sales.sold_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countSearch(?string $searchTerm): int
    {
        [$where, $params] = $this->searchConditions($searchTerm);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sales
             LEFT JOIN articles ON articles.id = sales.article_id
             LEFT JOIN customers ON customers.id = sales.customer_id
             {$where}"
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function searchConditions(?string $searchTerm): array
    {
        $searchTerm = $searchTerm !== null ? trim($searchTerm) : null;
        if ($searchTerm === null || $searchTerm === '') {
            return ['', []];
        }

        return [
            'WHERE articles.name LIKE :term OR customers.company_name LIKE :term2 OR customers.first_name LIKE :term3 OR customers.last_name LIKE :term4',
            [
                'term' => '%' . $searchTerm . '%',
                'term2' => '%' . $searchTerm . '%',
                'term3' => '%' . $searchTerm . '%',
                'term4' => '%' . $searchTerm . '%',
            ],
        ];
    }

    private function hydrate(array $row): Sale
    {
        return new Sale(
            id: $row['id'],
            inventoryNumberId: $row['inventory_number_id'],
            articleId: $row['article_id'],
            quantity: (int) $row['quantity'],
            customerId: $row['customer_id'],
            invoiceId: $row['invoice_id'],
            salePrice: $row['sale_price'],
            costOfGoodsSold: $row['cost_of_goods_sold'],
            shippingAmount: $row['shipping_amount'],
            soldAt: new DateTimeImmutable($row['sold_at']),
            note: $row['note'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
