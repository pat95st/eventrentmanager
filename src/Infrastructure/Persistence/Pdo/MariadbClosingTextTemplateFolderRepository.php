<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolder;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolderRepositoryInterface;
use DateTimeImmutable;
use PDO;

/** Siehe MariadbCategoryRepository für das identische Baumstruktur-Muster. */
final class MariadbClosingTextTemplateFolderRepository implements ClosingTextTemplateFolderRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ClosingTextTemplateFolder $folder): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO closing_text_template_folders (id, name, parent_id, created_at, updated_at)
             VALUES (:id, :name, :parent_id, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = :name2, parent_id = :parent_id2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parentId,
            'created_at' => $folder->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $folder->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $folder->name,
            'parent_id2' => $folder->parentId,
            'updated_at2' => $folder->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ClosingTextTemplateFolder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM closing_text_template_folders WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM closing_text_template_folders ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findChildren(?string $parentId): array
    {
        if ($parentId === null) {
            $stmt = $this->pdo->prepare('SELECT * FROM closing_text_template_folders WHERE parent_id IS NULL ORDER BY name ASC');
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM closing_text_template_folders WHERE parent_id = :parent_id ORDER BY name ASC');
            $stmt->execute(['parent_id' => $parentId]);
        }

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findDescendantIds(string $folderId): array
    {
        // Kleine Rekursion in PHP statt CTE, siehe MariadbCategoryRepository
        // für die identische Begründung (ältere MariaDB-Versionen < 10.2).
        $descendants = [];
        $stack = [$folderId];

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
        $sql = 'SELECT COUNT(*) FROM closing_text_template_folders WHERE name = :name AND ' .
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
        $stmt = $this->pdo->prepare('DELETE FROM closing_text_template_folders WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): ClosingTextTemplateFolder
    {
        return new ClosingTextTemplateFolder(
            id: $row['id'],
            name: $row['name'],
            parentId: $row['parent_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
