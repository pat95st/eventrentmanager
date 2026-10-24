<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Purchase\PurchaseOrder;
use App\Domain\Purchase\PurchaseOrderItem;
use App\Domain\Purchase\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\PurchaseOrderStatus;
use App\Domain\Purchase\PurchaseTargetType;
use DateTimeImmutable;
use PDO;

final class MariadbPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PurchaseOrder $order): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO purchase_orders (id, supplier_id, status, ordered_at, note, created_at, updated_at)
             VALUES (:id, :supplier_id, :status, :ordered_at, :note, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE supplier_id = :supplier_id2, status = :status2, ordered_at = :ordered_at2,
                note = :note2, updated_at = :updated_at2'
        );

        $orderedAt = $order->orderedAt?->format('Y-m-d H:i:s');
        $stmt->execute([
            'id' => $order->id,
            'supplier_id' => $order->supplierId,
            'status' => $order->status->value,
            'ordered_at' => $orderedAt,
            'note' => $order->note,
            'created_at' => $order->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $order->updatedAt->format('Y-m-d H:i:s'),
            'supplier_id2' => $order->supplierId,
            'status2' => $order->status->value,
            'ordered_at2' => $orderedAt,
            'note2' => $order->note,
            'updated_at2' => $order->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?PurchaseOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM purchase_orders WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function search(?string $searchTerm, ?PurchaseOrderStatus $status, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $status);

        $sql = 'SELECT po.* FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id'
            . $where . ' ORDER BY po.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countSearch(?string $searchTerm, ?PurchaseOrderStatus $status): int
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $status);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $searchTerm, ?PurchaseOrderStatus $status): array
    {
        $sql = ' WHERE 1 = 1';
        $params = [];

        if ($searchTerm !== null && trim($searchTerm) !== '') {
            $sql .= ' AND (s.name LIKE :search OR po.note LIKE :search2)';
            $needle = '%' . $searchTerm . '%';
            $params['search'] = $needle;
            $params['search2'] = $needle;
        }

        if ($status !== null) {
            $sql .= ' AND po.status = :status';
            $params['status'] = $status->value;
        }

        return [$sql, $params];
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM purchase_orders WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findItemsByOrder(string $purchaseOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT poi.* FROM purchase_order_items poi
             INNER JOIN articles a ON a.id = poi.article_id
             WHERE poi.purchase_order_id = :purchase_order_id
             ORDER BY a.name ASC'
        );
        $stmt->execute(['purchase_order_id' => $purchaseOrderId]);

        return array_map($this->hydrateItem(...), $stmt->fetchAll());
    }

    public function findItemById(string $id): ?PurchaseOrderItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM purchase_order_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateItem($row) : null;
    }

    public function saveItem(PurchaseOrderItem $item): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE purchase_order_items SET ordered_quantity = :ordered_quantity, unit_price = :unit_price,
                received_quantity = :received_quantity WHERE id = :id'
        );
        $stmt->execute([
            'ordered_quantity' => $item->orderedQuantity,
            'unit_price' => $item->unitPrice,
            'received_quantity' => $item->receivedQuantity,
            'id' => $item->id,
        ]);
    }

    public function replaceItems(string $purchaseOrderId, array $items): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM purchase_order_items WHERE purchase_order_id = :purchase_order_id');
            $delete->execute(['purchase_order_id' => $purchaseOrderId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO purchase_order_items (id, purchase_order_id, article_id, target_type, ordered_quantity, unit_price, received_quantity)
                 VALUES (:id, :purchase_order_id, :article_id, :target_type, :ordered_quantity, :unit_price, :received_quantity)'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'id' => $item->id,
                    'purchase_order_id' => $item->purchaseOrderId,
                    'article_id' => $item->articleId,
                    'target_type' => $item->targetType->value,
                    'ordered_quantity' => $item->orderedQuantity,
                    'unit_price' => $item->unitPrice,
                    'received_quantity' => $item->receivedQuantity,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    private function hydrate(array $row): PurchaseOrder
    {
        return new PurchaseOrder(
            id: $row['id'],
            supplierId: $row['supplier_id'],
            status: PurchaseOrderStatus::from($row['status']),
            orderedAt: $row['ordered_at'] !== null ? new DateTimeImmutable($row['ordered_at']) : null,
            note: $row['note'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    private function hydrateItem(array $row): PurchaseOrderItem
    {
        return new PurchaseOrderItem(
            id: $row['id'],
            purchaseOrderId: $row['purchase_order_id'],
            articleId: $row['article_id'],
            targetType: PurchaseTargetType::from($row['target_type']),
            orderedQuantity: (int) $row['ordered_quantity'],
            unitPrice: $row['unit_price'],
            receivedQuantity: (int) $row['received_quantity'],
        );
    }
}
