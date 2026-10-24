<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Category\Category;
use App\Domain\Category\CategoryRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Category $category): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (id, name, parent_id, created_at, updated_at)
             VALUES (:id, :name, :parent_id, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = :name2, parent_id = :parent_id2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $category->id,
            'name' => $category->name,
            'parent_id' => $category->parentId,
            'created_at' => $category->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $category->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $category->name,
            'parent_id2' => $category->parentId,
            'updated_at2' => $category->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM categories ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findChildren(?string $parentId): array
    {
        if ($parentId === null) {
            $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name ASC');
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE parent_id = :parent_id ORDER BY name ASC');
            $stmt->execute(['parent_id' => $parentId]);
        }

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findDescendantIds(string $categoryId): array
    {
        // Kleine Rekursion in PHP statt CTE, damit auch ältere MariaDB-Versionen (<10.2) funktionieren.
        $descendants = [];
        $stack = [$categoryId];

        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($this->findChildren($current) as $child) {
                $descendants[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $descendants;
    }

    public function existsByNameAndParent(string $name, ?string $parentId, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE name = :name AND ' .
            ($parentId === null ? 'parent_id IS NULL' : 'parent_id = :parent_id');

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['name' => $name];
        if ($parentId !== null) {
            $params['parent_id'] = $parentId;
        }
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Category
    {
        return new Category(
            id: $row['id'],
            name: $row['name'],
            parentId: $row['parent_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
