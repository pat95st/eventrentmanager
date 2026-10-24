<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\CaseContent\CaseContentItem;
use App\Domain\CaseContent\CaseContentItemRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbCaseContentItemRepository implements CaseContentItemRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(CaseContentItem $item): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_content_items (id, case_inventory_number_id, article_id, quantity, created_at, updated_at)
             VALUES (:id, :case_inventory_number_id, :article_id, :quantity, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE quantity = :quantity2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $item->id,
            'case_inventory_number_id' => $item->caseInventoryNumberId,
            'article_id' => $item->articleId,
            'quantity' => $item->quantity,
            'created_at' => $item->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $item->updatedAt->format('Y-m-d H:i:s'),
            'quantity2' => $item->quantity,
            'updated_at2' => $item->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?CaseContentItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM case_content_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCaseInventoryNumber(string $caseInventoryNumberId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ci.* FROM case_content_items ci
             INNER JOIN articles a ON a.id = ci.article_id
             WHERE ci.case_inventory_number_id = :case_inventory_number_id
             ORDER BY a.name ASC'
        );
        $stmt->execute(['case_inventory_number_id' => $caseInventoryNumberId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function existsForCaseAndArticle(string $caseInventoryNumberId, string $articleId, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM case_content_items
                WHERE case_inventory_number_id = :case_inventory_number_id AND article_id = :article_id';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['case_inventory_number_id' => $caseInventoryNumberId, 'article_id' => $articleId];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isUsedByArticle(string $articleId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM case_content_items WHERE article_id = :article_id');
        $stmt->execute(['article_id' => $articleId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM case_content_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): CaseContentItem
    {
        return new CaseContentItem(
            id: $row['id'],
            caseInventoryNumberId: $row['case_inventory_number_id'],
            articleId: $row['article_id'],
            quantity: (int) $row['quantity'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
