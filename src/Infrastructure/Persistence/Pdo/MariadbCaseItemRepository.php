<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\CaseItem\CaseItem;
use App\Domain\CaseItem\CaseItemRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbCaseItemRepository implements CaseItemRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(CaseItem $caseItem): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_items (id, name, manufacturer_id, prefix, description, created_at, updated_at)
             VALUES (:id, :name, :manufacturer_id, :prefix, :description, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                name = :name2, manufacturer_id = :manufacturer_id2, prefix = :prefix2,
                description = :description2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $caseItem->id,
            'name' => $caseItem->name,
            'manufacturer_id' => $caseItem->manufacturerId,
            'prefix' => $caseItem->prefix,
            'description' => $caseItem->description,
            'created_at' => $caseItem->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $caseItem->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $caseItem->name,
            'manufacturer_id2' => $caseItem->manufacturerId,
            'prefix2' => $caseItem->prefix,
            'description2' => $caseItem->description,
            'updated_at2' => $caseItem->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?CaseItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM case_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function search(?string $searchTerm, ?string $manufacturerId, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $manufacturerId);

        $sql = 'SELECT * FROM case_items' . $where . ' ORDER BY name ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countSearch(?string $searchTerm, ?string $manufacturerId): int
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $manufacturerId);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM case_items' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $searchTerm, ?string $manufacturerId): array
    {
        $sql = ' WHERE 1 = 1';
        $params = [];

        if ($searchTerm !== null && $searchTerm !== '') {
            $sql .= ' AND (name LIKE :term OR description LIKE :term)';
            $params['term'] = '%' . $searchTerm . '%';
        }

        if ($manufacturerId !== null) {
            $sql .= ' AND manufacturer_id = :manufacturer_id';
            $params['manufacturer_id'] = $manufacturerId;
        }

        return [$sql, $params];
    }

    public function countByManufacturer(string $manufacturerId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM case_items WHERE manufacturer_id = :manufacturer_id');
        $stmt->execute(['manufacturer_id' => $manufacturerId]);

        return (int) $stmt->fetchColumn();
    }

    public function existsByPrefix(string $manufacturerId, string $prefix, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM case_items WHERE manufacturer_id = :manufacturer_id AND prefix = :prefix';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['manufacturer_id' => $manufacturerId, 'prefix' => $prefix];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM case_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): CaseItem
    {
        return new CaseItem(
            id: $row['id'],
            name: $row['name'],
            manufacturerId: $row['manufacturer_id'],
            prefix: $row['prefix'],
            description: $row['description'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
